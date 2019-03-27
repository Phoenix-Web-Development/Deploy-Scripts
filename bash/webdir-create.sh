#!/bin/bash
projectDir=$1
webDir=$2
owner=$3
group=$4


### check if directory exists or not
#if ! [ -d "$projectDir" ]; then
	### create the directory
#	mkdir -p $projectDir
#	if ! [ -d "$projectDir" ]; then
#	    echo -e $"Couldn't create project directory."
#	    exit;
#	fi
#fi


### check if directory exists or not
echo -e $"$webDir"
if ! [[ -d "$webDir" ]]; then
	### create the directory
	mkdir -p $webDir
	if ! [[ -d "$webDir" ]]; then
	    echo -e $"Couldn't create web directory."
	    exit;
	fi
fi


### give permission to root dir
chmod 755 $webDir
### write test file in the new web dir
if ! echo "<?php echo phpinfo(); ?>" > $webDir/phpinfo.php
then
	echo $"ERROR: Not able to write in file $webDir/phpinfo.php. Please check permissions"
	exit;
else
	echo $"Added content to $webDir/phpinfo.php"
fi
chown -R $owner:$group $projectDir


### show the finished message
echo -e $"Successfully created web directory at $webDir"
exit;