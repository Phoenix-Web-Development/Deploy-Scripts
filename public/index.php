<?php

namespace Phoenix;

//use Phoenix;
require_once '../vendor/autoload.php';

//if ( !class_exists( 'Deployer' ) )
//  exit( 'Didn\'t initialise properly.' );
$deploy = new Deployer();
ph_d()->run();