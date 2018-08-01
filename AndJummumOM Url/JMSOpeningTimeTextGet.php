<?php
    include_once("dbConnect.php");
    if($_GET["dbName"])
    {
        $_POST["dbName"] = $_GET["dbName"];
    }
    setConnectionValue($_POST["dbName"]);
    writeToLog("file: " . basename(__FILE__) . ", user: " . $_POST["modifiedUser"]);
    printAllPost();
    ini_set("memory_limit","-1");
    

    
    
    
    if(isset($_POST["dbName"]))
    {
        $dbName = $_POST["dbName"];
    }
    
    
    
    
    // Check connection
    if (mysqli_connect_errno())
    {
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
    }
    
    
    
    
    $sql = "select * from openingTime order by day,shiftNo";
    $selectedRow = getSelectedRow($sql);
    for($i=0; $i<sizeof($selectedRow); $i++)
    {
        if($dayOfWeek != intval($selectedRow[$i]["Day"]))
        {
            $dayOfWeek = $selectedRow[$i]["Day"];
            $startTime = $selectedRow[$i]["StartTime"];
            $endTime = $selectedRow[$i]["EndTime"];
            $text = $text == ""?"":$text."\n";
            $text .= getDayOfWeekText($dayOfWeek) . "\t" . $startTime . " - " . $endTime;
        }
        else
        {
            $startTime = $selectedRow[$i]["StartTime"];
            $endTime = $selectedRow[$i]["EndTime"];
            $text = $text == ""?"":$text."\n";
            $text .= "\t\t" . $startTime . " - " . $endTime;
        }
    }
    
    $sql = "select '$text' as Text;";
    
    
    /* execute multi query */
    $jsonEncode = executeMultiQuery($sql);
    echo $jsonEncode;


    
    // Close connections
    mysqli_close($con);
    
?>
