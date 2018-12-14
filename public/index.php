<?php

namespace Phoenix;

//use Phoenix;
require_once '../vendor/autoload.php';


//if ( !class_exists( 'Deployer' ) )
//  exit( 'Didn\'t initialise properly.' );

$deploy = new Deployer();
ph_d()->run();


/*
$blegh = array(
    'secret' => 'M$Rx2eyEyQmx',
    'worktree' => '/home2/phoenixstaging1/staging/wibble/public_html');
echo json_encode($blegh);
*/