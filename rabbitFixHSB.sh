#!/bin/bash


# Change IP depending on what server will be getting pinged


mainIP="100.89.10.38"
iniMain=/var/hsb/main/testRabbitMQ.ini
iniBackUp=/var/hsb/backup/testRabbitMQ.ini
iniCurrent="$iniMain"
pingIntervalInSeconds=15            
pingFailInSeconds=5               
pingAttempts=3    
isMainOn=true          


pingServer() {
    if ping -c "$pingAttempts" -W "$pingFailInSeconds" "$mainIP" > /dev/null 2>&1; then
        isMainOn=true
        echo "Ping successful"
    else
        isMainOn=false 
        echo "Ping failed"
    fi
}


changeIP() {
    iniNew=$1
    cp "$iniNew" /home/craig/git/it490-Final-Submission/testRabbitMQ.ini
    iniCurrent="$iniNew"
    systemctl restart apache2
    echo "Switch to: $iniCurrent"
}


# Below is the main checks running


while true; do
    pingServer
    
    if [ "$isMainOn" = true ] && [ "$iniCurrent" = "$iniBackUp" ]; then
        echo "Main is back up -> switch"
        changeIP "$iniMain"
    elif [ "$isMainOn" = false ] && [ "$iniCurrent" = "$iniMain" ]; then
        echo "Main is down -> switch"
        changeIP "$iniBackUp"
    else
        echo "Do nothing"
    fi
    
    # Interval between pings to check server status
    sleep "$pingIntervalInSeconds"
done


