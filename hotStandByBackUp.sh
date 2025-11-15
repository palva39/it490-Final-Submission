#!/bin/bash


# Change IP depending on what server will be getting pinged


otherIP="" 
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


startDB() {
   echo "Starting DB"
   systemctl start db
}


stopDB() {
   echo "Stopping DB"
   systemctl stop db
}


# Below is the main checks running


while true; do
   pingServer


   if [ "$isBackup" = true ]; then
       if [ "$alreadyRunning" = true ]; then
           stopDB
           alreadyRunning=false
       fi
   else
       if [ "$alreadyRunning" = false ]; then
           startDB
           alreadyRunning=true
       fi
   fi
  
   # Interval between pings to check server status
   sleep "$pingIntervalInSeconds"


done

