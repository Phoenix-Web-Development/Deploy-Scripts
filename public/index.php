<?php

namespace Phoenix;

//use Phoenix;
require_once '../vendor/autoload.php';

//if ( !class_exists( 'Deployer' ) )
// exit( 'Didn\'t initialise properly.' );
new Deployer();
ph_d()->init();
//ph_d();
$template = new \Phoenix\Template();
$template->get('header');

if (!ph_d()->can_do('create') && !ph_d()->can_do('delete')) {
    template()->get('form');
} else {
    if (ph_d()->can_do('create')) {
        if (ph_d()->can_do('create_version_control')) {
            ph_d()->versionControlMainRepo('create');
        }
        if (ph_d()->can_do('create_live_stuff'))
            ph_d()->create_live_stuff();
        if (ph_d()->can_do('create_staging_stuff'))
            ph_d()->create_staging_stuff();
        if (ph_d()->can_do('create_local_stuff'))
            ph_d()->localStuff('create');

        logger()->add('<h2>Finished Deploying</h2>', 'info');
    } elseif (ph_d()->can_do('delete')) {
        if (ph_d()->can_do('delete_version_control'))
            ph_d()->versionControlMainRepo('delete');
        if (ph_d()->can_do('delete_live_stuff'))
            ph_d()->delete_live_stuff();
        if (ph_d()->can_do('delete_staging_stuff'))
            ph_d()->delete_staging_stuff();
        if (ph_d()->can_do('delete_local_stuff'))
            ph_d()->localStuff('delete');

        logger()->add('<h2>Finished Deleting</h2>', 'info');
    }


}

template()->get('footer');