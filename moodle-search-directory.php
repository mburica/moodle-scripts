<?php

function theme_boston_get_injection($layout, $PAGE, $filename = 'lib.php', $dir = 'local') {
    global $CFG;

    $dir = $CFG->dirroot . '/' . $dir; 

    foreach(new DirectoryIterator($dir) as $item) {
        if($item->isDir() && !$item->isDot()) { 
            $filePath = $item->getPathname() . '/' . $filename;
            if(file_exists($filePath)) {
                $file = join("\n",file($filePath));
                preg_match_all('/function\s+(\w+)/', $file, $m);
                $functions = $m[1];

                foreach($functions as $function) {
                    if(strpos($function, 'theme_injection') !== false) { 
                        $last_us = strrpos($function, '_');
                        $injection_loc = substr($function, $last_us + 1);
                        
                        if($injection_loc == $layout) {
                            require_once $filePath;
                            call_user_func($function, $PAGE);
                        }
                    }
                }
            }
        }
    }
}
