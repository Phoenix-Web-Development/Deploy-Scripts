#!/bin/bash
path=$1
### check if directory exists or not
if [[ -d "$path" ] || [ -f "$path" ]]; then
    echo -e $"File $path exists"
	exit;
fi

echo -e $"No file at $path"
exit;