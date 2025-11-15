#!/bin/bash

# Change IP depending on what server will be getting pinged

otherIP="100.71.80.77"  
pingIntervalInSeconds=15             
pingFailInSeconds=5                
pingAttempts=3     
isBackup=true     
alreadyRunning=false       

pingServer() {
    if (ping -c "$pingAttempts" -W "$pingFailInSeconds" "$otherIP" > /dev/null 2>&1); then
        isBackup=true
    else 
        isBackup=false  
    fi
}

startApache() {
    echo "Starting Apache"
    systemctl start apache2 
}

stopApache() {
    echo "Stopping Apache"
    systemctl stop apache2 
}

# Below is the main checks running

while true; do
    pingServer

    if [ "$isBackup" = true ]; then
        if [ "$alreadyRunning" = true ]; then
            stopApache
            currentlyRunning=false
        fi
    else
        startApache
    fi 
    
    # Interval between pings to check server status
    sleep "$pingIntervalInSeconds"

done