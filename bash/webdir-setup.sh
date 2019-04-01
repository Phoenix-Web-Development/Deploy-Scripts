#!/bin/bash
webDir=$1
webOwner=$2
webGroup=$3
projectDir=$4
projectOwner=$5
projectGroup=$6

### check if directory exists or not as security measure. We only want to play with permissions of a folder that doesn't yet exist
if [[ -d "$webDir" ]]; then
    echo -e $"Web directory already exists. Potential security issue to change permissions on already existing directory."
	exit;
fi
if [[ ! -z "$projectDir" ]]; then
    if [[ -d "$projectDir" ]]; then
        echo -e $"Project directory already exists. Potential security issue to change permissions on already existing directory."
	    exit;
    fi

    if [[ "$webDir" != "$projectDir"* ]]; then
        echo "Web directory is not subdirectory of Project directory."
        exit;
    fi
fi


### create the directory
mkdir -p "$webDir"

### set owners
finishedMesssage=""
if [[ ! -z "$projectDir" ]]; then
    chown -R "$projectOwner:$projectGroup" "$projectDir"
    finishedMesssage=" Successfully setup project directory at $projectDir."
fi
chown -R "$webOwner:$webGroup" "$webDir"
### give permission to root dir
chmod 770 "$webDir"
###set new files and dirs to have same group as parent directory. Command is recursive for any sub dirs
chmod g+s "$webDir"
###set new files and folders under webdir to have 770 permissions as per facl. Command is recursive for any sub dirs
setfacl -R -d -m u::rwx,g::rwx,o::rx "$webDir"




### show the finished message
echo -e $"Successfully setup web directory at $webDir.$finishedMesssage"
exit;