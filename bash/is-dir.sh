#!/bin/bash
dir=$1
### check if directory exists or not
if [[ -d "$dir" ]]; then
    echo -e $"Directory $dir exists"
	exit;
fi
echo -e $"No directory at $dir"
exit;