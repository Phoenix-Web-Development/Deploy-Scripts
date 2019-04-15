#!/bin/bash
dir=$1
owner=$2
group=$3
projectDir=$4
projectOwner=$5
projectGroup=$6

### check if directory exists or not as security measure. We only want to play with permissions of a folder that doesn't yet exist
if [[ -d "$dir" ]]; then
    echo -e $"Web directory already exists. Potential security issue to change permissions on already existing directory."
	exit;
fi

if [[ -z "$projectDir" ]]; then
    echo -e $"Project directory not provided to script."
	exit;
fi
projectDirLength=(expr length "$projectDir")
dirLength=(expr length "$dir")

if [[ "$projectDirLength" == "$dirLength" ]]; then
    if [[ "$projectDir" == "$dir"*  ]]; then
        echo "Project directory should not be a sub-directory of the nominated directory."
        exit;
    fi
fi

if [[ -d "$projectDir" ]]; then
    projectDirMessage=" No need to setup project directory as it already exists."
else
    ### create the directory
    mkdir "$projectDir"
    ### set owner
    chown -R "$projectOwner:$projectGroup" "$projectDir"
    projectDirMessage=" Successfully setup project directory at $projectDir."
fi


### create the directory
mkdir -p "$dir"
### set owner
chown -R "$owner:$group" "$dir"
### give permission to root dir
chmod 770 "$dir"
###set new files and dirs to have same group as parent directory. Command is recursive for any sub dirs
chmod g+s "$dir"
###set new files and folders under dir to have 770 permissions as per facl. Command is recursive for any sub dirs
setfacl -R -d -m u::rwx,g::rwx,o::rx "$dir"

### show the finished message
echo -e $"Successfully setup directory at $dir.$projectDirMessage"
exit;