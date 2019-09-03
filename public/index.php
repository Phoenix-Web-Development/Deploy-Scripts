<?php

namespace Phoenix;

//use Phoenix;
require_once '../vendor/autoload.php';

//if ( !class_exists( 'Deployer' ) )
//  exit( 'Didn\'t initialise properly.' );
$deploy = new Deployer();
$deploy->run();



//wp scaffold child-theme dpr-activia-child --parent_theme=dpr-activia --author="James Jones" --theme_name="DPR Activia Child" --author_uri=https://phoenixweb.com.au/ --theme_uri=https://phoenixweb.com.au/