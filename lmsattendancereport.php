<?php

define('CLI_SCRIPT', true);

if(str_replace('-', '', $argv[1]) == 'help') {
    echo 'Tardy Student Report Generator' . PHP_EOL;
    echo '
        Description: Runs a Tardy Student Report and saves the output to a .csv file [tardyStudentReport_currenttimestamp.csv]
        The report excludes the Site course and defaults to minimum 3 and maximum 70 absences.
        The script is required to be in the admin/cli directory as Moodle config so that it has access to the DB info.
        It will automatically run the report against the DB that the Moodle config.php is set to and save the report in the tmp directory
        as the script by default.
        Once report is generated it is then uploaded to the busapps SFTP.
    ' . PHP_EOL;
    echo 'Usage: tardyStudentReport.php [options]' . PHP_EOL;
    echo PHP_EOL;
    echo "\t". '--program="PROGRAMCODE"' . "\t\t" . 'Required' . PHP_EOL;
    echo "\t". '--school="SCHOOLNAME"' . "\t\t" . 'Required' . PHP_EOL;
    echo "\t". '--minabs="INT"' . "\t\t\t" . 'Optional - Minimum absences needed to be included' . PHP_EOL;
    echo "\t". '--maxabs="INT"' . "\t\t\t" . 'Optional - Maximum absences needed to be included' . PHP_EOL;
    echo "\t". '--run="TYPE"' . "\t\t\t" . 'Optional - daily, hourly, test. Specifies the file save location on the SFTP server, defaults to test.' . PHP_EOL;
    exit();
}

// Gracefully handle '=' in args
$_ARGS = [];
foreach($argv as $arg) {
    if (preg_match('/--([^=]+)=(.*)/', $arg, $reg)) {
        $_ARGS[$reg[1]] = $reg[2];
    } elseif(preg_match('/-([a-zA-Z0-9])/', $arg, $reg)) {
        $_ARGS[$reg[1]] = 'true';
    }
}

// Check that necessary arguments exist
if(!isset($_ARGS['program']) || !isset($_ARGS['school'])) {
    echo 'One or more arguments is missing' . PHP_EOL;
    exit();
}

// Set defaults for min/max absences if not set properly
$min = isset($_ARGS['minabs']) && is_int($_ARGS['minabs']) ? $_ARGS['minabs'] : 3;
$max = isset($_ARGS['maxabs']) && is_int($_ARGS['maxabs']) ? $_ARGS['maxabs'] : 70;

// Check that have access to the config file for DB info
if(!file_exists(__DIR__.'/../../config.php')) {
    echo 'Script cannot find config file for DB connection' . PHP_EOL;
    exit();
} else {
    include __DIR__.'/../../config.php';
}

global $CFG;

mtrace(usertimezone());

// Attempt to connect to the DB
$db = mysqli_connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass, $CFG->dbname);

if(!$db) {
    echo 'Failed to connect to Moodle DB' . PHP_EOL;
    exit();
} else {
    echo 'Successfully connected to Moodle DB...' . PHP_EOL;
}

// Run the report
echo 'Running Tardy Student Report...' . PHP_EOL;

$school = $_ARGS['school'];
$program = $_ARGS['program'];

$sql = "
(
    SELECT
        u.username AS LMSstudentID,
        u.lastname AS lastName,
        u.firstname AS firstName,
        u.email AS email,
        '$school' AS schoolName,
        '$program' AS programName,
        c.shortname AS courseName,
        lc.daysnologin,
        lc.lastActivity,
        CONVERT(CONVERT(u.idnumber, UNSIGNED INTEGER), CHAR) AS LaurusStudentID,
        c.idnumber AS LaurusCourseID
    FROM
        mdl_user u,
        mdl_course c,
        mdl_role_assignments ra,
        mdl_role r,
        mdl_context cxt,
       ( SELECT
           l.userid AS userid,
           l.courseid AS courseid,
           datediff(current_date(),(date(FROM_UNIXTIME(max(l.timecreated))) + INTERVAL 1 DAY )) AS daysnologin,
           max(l.timecreated) AS lastActivity
        FROM
           mdl_logstore_standard_log l
        WHERE
           l.courseid NOT IN (0,1)
       GROUP BY l.userid, l.courseid
       HAVING (datediff(current_date(),(date(FROM_UNIXTIME(max(l.timecreated))) + INTERVAL 1 DAY )) >= $min)
         AND (datediff(current_date(),(date(FROM_UNIXTIME(max(l.timecreated))) + INTERVAL 1 DAY )) <= $max)
       ) AS lc
    WHERE
        lc.userid = u.id
        AND lc.courseid = c.id
        AND ra.userid = u.id
        AND ra.contextid = cxt.id
        AND cxt.contextlevel >= 50
        AND cxt.instanceid = c.id
        AND ra.roleid = 5
        AND u.auth <> 'manual'
        AND u.deleted = 0
        AND u.suspended = 0
        AND r.id = ra.roleid
        AND cxt.id = ra.contextid
        AND c.id = cxt.instanceid
    )
    UNION
    (
    SELECT
        u.username AS LMSstudentID,
        u.lastname AS lastName,
        u.firstname AS firstName,
        u.email AS email,
        '$school' AS schoolName,
        '$program' AS programName,
        c.shortname AS courseName,
        datediff(current_date(),(date(FROM_UNIXTIME(c.startdate - 300)) + INTERVAL 1 DAY )) AS daysnologin,
        'NULL' AS lastActivity,
        CONVERT(CONVERT(u.idnumber, UNSIGNED INTEGER), CHAR) AS LaurusStudentID,
        c.idnumber AS LaurusCourseID
    FROM
        mdl_role_assignments ra
        JOIN mdl_user u ON u.id = ra.userid
        JOIN mdl_role r ON r.id = ra.roleid
        JOIN mdl_context cxt ON cxt.id = ra.contextid
        JOIN mdl_course c ON c.id = cxt.instanceid
        LEFT JOIN (
            SELECT lc.userid, lc.courseid, max(lc.timecreated)
            FROM
            (
                SELECT
                l.userid AS userid,
                l.courseid AS courseid,
                l.timecreated AS timecreated
                FROM mdl_logstore_standard_log l
                WHERE
                (datediff(current_date(),(date(FROM_UNIXTIME(l.timecreated)) + INTERVAL 1 DAY )) >= $min)
                    AND (datediff(current_date(),(date(FROM_UNIXTIME(l.timecreated)) + INTERVAL 1 DAY )) <= $max)
            ) AS lc
            GROUP BY lc.timecreated
        ) AS lcc ON lcc.userid = u.id and lcc.courseid = c.id
    WHERE
      ra.userid = u.id
      AND ra.contextid = cxt.id
      AND cxt.contextlevel >= 50
      AND cxt.instanceid = c.id
      AND ra.roleid = 5
      AND u.auth <> 'manual'
      AND u.deleted = 0
      AND u.suspended = 0
      AND c.id NOT IN (0,1)
      AND lcc.userid IS NULL
      AND (datediff(current_date(),(date(FROM_UNIXTIME(c.startdate - 300)) + INTERVAL 1 DAY )) >= $min)
      AND (datediff(current_date(),(date(FROM_UNIXTIME(c.startdate - 300)) + INTERVAL 1 DAY )) <= $max)
    )
    order by courseName, LMSstudentID, lastActivity
";

if($result = $db->query($sql)) {
    echo 'Report returned ' . $result->num_rows . ' rows' . PHP_EOL;
    echo 'Writing report to file...' . PHP_EOL;

    if($result->num_rows > 0) {
        $delimiter = ',';
        $filelocation = '/tmp//';
        $filename = 'LMS_' . $school . '_' . date('Y_m_d_H_i') . '.txt';

        // Create file pointer
        $f = fopen($filelocation . $filename, 'w');

        // Set column headers
        //$headers = ['studentID', 'lastName', 'firstName', 'email', 'schoolName', 'programName', 'courseName', 'daysnologin', 'lastActivity', 'laurusStudentID', 'laurusCourseID'];
        fwrite($f, 'studentID,lastName,firstName,email,schoolName,programName,courseName,daysnologin,lastActivity,laurusStudentID,laurusCourseID' . "\r\n");

        // Get all suspended users
        $suspendedusers = $DB->get_records_sql("
            SELECT
                u.username, GROUP_CONCAT(c.shortname) courses
            FROM mdl_user_enrolments ue
            JOIN mdl_enrol e ON e.id = ue.enrolid
            JOIN mdl_course c ON c.id = e.courseid
            JOIN mdl_user u ON u.id = ue.userid
            WHERE
                ue.status = 1
            GROUP BY u.username
        ");

        // Output query results to file
        while($row = $result->fetch_assoc()) {
            // Check if record represents a suspended user
            if(isset($suspendedusers[$row['LMSstudentID']])) {
                $suspendedcourses = explode(',', $suspendedusers[$row['LMSstudentID']]->courses);

                if(in_array($row['courseName'], $suspendedcourses)) {
                    // skip if suspended in course
                    continue;
                }
            }

            if($row['lastActivity'] != 'NULL' && !empty($row['lastActivity'])) {
                $lastactivity = date('Y-m-d H:i:s', $row['lastActivity']);
            } else {
                $lastactivity = $row['lastActivity'];
            }

            $rowdata = [
                $row['LMSstudentID'], $row['lastName'], $row['firstName'], $row['email'], $row['schoolName'], $row['programName'],
                $row['courseName'], $row['daysnologin'], $lastactivity, $row['LaurusStudentID'], $row['LaurusCourseID'],
            ];

            $writable = '';
            foreach($rowdata as $key => $data) {
                $writable .= !empty($data) ? '"' . str_replace('"', '""', $data) . '",' : ',';
            }

            //fputcsv($f, $rowdata, $delimiter);
            fwrite($f, $writable . "\r\n");    // needs to differentiate from standard valid csv format used by PHP
        }
    }

    $result->close();

    if(file_exists($filelocation . $filename)) {
        echo 'Report successfully created' . PHP_EOL;
        echo 'Uploading report to SFTP...' . PHP_EOL;

        // SFTP info
        $host = "";
        $port = 22;
        $user = "";
        $pass = "";

        // Connect to SFTP
        if(!$ssh = ssh2_connect($host, $port)) {
            echo 'Failed to establish SSH connection to SFTP server' . PHP_EOL;
            fclose($stream);
            exit();
        }
        if(!ssh2_auth_password($ssh, $user, $pass)) {
            echo 'Failed to authenticate to SFTP server' . PHP_EOL;
            fclose($stream);
            exit();
        }
        if(!$sftp = ssh2_sftp($ssh)) {
            echo 'Failed to create a SFTP connection' . PHP_EOL;
            fclose($stream);
            exit();
        }

        // Get save location
        switch(strtolower($_ARGS['run'])) {
            case 'daily':
                $dir = 'daily';
                break;
            case 'hourly':
                $dir = 'hourly';
                break;
            default:
                $dir = 'test';
        }

        // Write local file to SFTP
        $stream = fopen("ssh2.sftp://$sftp/$dir/$filename", 'w');
        $file = file_get_contents($filelocation . $filename);
        fwrite($stream, $file);
        fclose($stream);

        // Check if file uploaded
        if(!$remote = fopen("ssh2.sftp://$sftp/$dir/$filename", 'r')) {
            echo 'Failed to find report on SFTP server' . PHP_EOL;
        } else {
            echo 'Report successfully uploaded to SFTP server' . PHP_EOL;
        }
    }
} else {
    echo 'Failed to generate report' . PHP_EOL;
    echo $result->error . PHP_EOL;
}

echo 'Exiting report generator' . PHP_EOL;

$db->close();
