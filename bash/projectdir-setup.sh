#!/bin/bash
dir=$1
owner=$2
group=$3
projectDir=$4
projectOwner=$5
projectGroup=$6

### check if directory exists or not as security measure. We only want to play with permissions of a folder that doesn't yet exist
if [[ -d "$dir" ]]; then
    actualOwner=$(stat -c "%U" "$dir")
    actualGroup=$(stat -c "%G" "$dir")
    permissionString="\nPermissions incorrect but potential security issue to change permissions on already existing directory."
    if [[ "$actualOwner" == "$owner" ]]; then
        if [[ "$actualGroup" == "$group" ]]; then
            permissionString=" \nNo need to proceed further."
        fi
    fi
    echo -e $"Directory already exists owned by user $actualOwner and group $actualGroup.$permissionString"
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
    projectDirMessage="\nno need to setup project directory as it already exists."
else
    # create the project directory
    mkdir -m 750 "$projectDir"
    creatingProjectDir=true
fi

# create the  directory
mkdir -m 770 -p "$dir"

if [[ "$creatingProjectDir" = true ]] ; then
    # set owner
    chown -R "$projectOwner:$projectGroup" "$projectDir"
    #web owner on subdirectories
    chown -R "$owner:$group" "$projectDir"/*
    chmod -R g+s "$projectDir"
    setfacl -R -d -m u::rwx,g::rwx "$projectDir"
    projectDirMessage="\nSuccessfully setup project directory at $projectDir."
fi

#set owner
chown -R "$owner:$group" "$dir"
#set new files and dirs to have same group as parent directory. Command is recursive for any sub dirs
chmod -R g+s "$dir"
#set new files and folders under dir to have 770 permissions as per facl. Command is recursive for any sub dirs
setfacl -R -d -m u::rwx,g::rwx "$dir"


# show the finished message
if [[ -d "$dir" ]]; then
    actualOwner=$(stat -c "%U" "$dir")
    actualGroup=$(stat -c "%G" "$dir")
    if [[ "$actualOwner" == "$owner" ]]; then
        ownerString="correct"
    else
        ownerString="incorrect"
    fi
    if [[ "$actualGroup" == "$group" ]]; then
        groupString="correct"
    else
        groupString="incorrect"
    fi
    echo -e $"Created directory at <strong>$dir</strong> with $ownerString owner <strong>$actualOwner</strong> and $groupString group <strong>$actualGroup</strong>.$projectDirMessage"
    exit;
fi

echo -e $"Failed to create directory at $dir."
exit;