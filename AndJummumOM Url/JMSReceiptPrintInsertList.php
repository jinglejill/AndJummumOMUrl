<?php    
    include_once("dbConnect.php");
    setConnectionValue($_POST["dbName"]);
    writeToLog("file: " . basename(__FILE__) . ", user: " . $_POST["modifiedUser"]);
    printAllPost();
    $dbName = $_POST["dbName"];
    
    
    if (isset($_POST["countReceiptPrint"]))
    {
        $countReceiptPrint = $_POST["countReceiptPrint"];
        for($i=0; $i<$countReceiptPrint; $i++)
        {
            $receiptPrintID[$i] = $_POST["receiptPrintID".sprintf("%02d", $i)];
            $receiptID[$i] = $_POST["receiptID".sprintf("%02d", $i)];
            $modifiedUser[$i] = $_POST["modifiedUser".sprintf("%02d", $i)];
            $modifiedDate[$i] = $_POST["modifiedDate".sprintf("%02d", $i)];
        }
    }
    
    
    
    // Check connection
    if (mysqli_connect_errno())
    {
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
    }
    
    
    
    // Set autocommit to off
    mysqli_autocommit($con,FALSE);
    writeToLog("set auto commit to off");
    
    
    
    if($countReceiptPrint > 0)
    {
        for($k=0; $k<$countReceiptPrint; $k++)
        {
            //query statement
            $sql = "INSERT INTO ReceiptPrint(ReceiptID, ModifiedUser, ModifiedDate) VALUES ('$receiptID[$k]', '$modifiedUser[$k]', '$modifiedDate[$k]')";
            $ret = doQueryTask($sql);
            if($ret != "")
            {
                mysqli_rollback($con);
                putAlertToDevice();
                echo json_encode($ret);
                exit();
            }
            
            
            
            //insert ผ่าน
            $newID = mysqli_insert_id($con);
            
        
    }
    
    
    //update receipt status at JUMMUM
    $sql = "select * from AND_JUMMUM_OM.Branch where dbName = '$dbName';";
    $selectedRow = getSelectedRow($sql);
    $branchID = $selectedRow[0]["BranchID"];
    
    
    if($countReceiptPrint > 0)
    {        
        $sql = "update AND_JUMMUM.receipt set status = 5, statusRoute = concat(statusRoute,',','5'), modifiedDate='$modifiedDate[0]', modifiedUser='$modifiedUser[0]' where branchID = '$branchID' and receiptID in ('$receiptID[0]'";
        for($k=1; $k<$countReceiptPrint; $k++)
        {
            $sql .= ",'$receiptID[$k]'";
        }
        $sql .= ")";
    }
    $ret = doQueryTask($sql);
    if($ret != "")
    {
        mysqli_rollback($con);
        putAlertToDevice();
        echo json_encode($ret);
        exit();
    }
    
    
    
    
    //do script successful
    //delete and insert ตัวเอง, insert คนอื่น สำหรับกรณี sync ให้ข้อมูล update เหมือนกันหมด
    mysqli_commit($con);
//    sendPushNotificationToAllDevices($_POST["modifiedDeviceToken"]);
    mysqli_close($con);
    
    
    
    writeToLog("query commit, file: " . basename(__FILE__) . ", user: " . $_POST['modifiedUser']);
    $response = array('status' => '1', 'sql' => $sql);
    echo json_encode($response);
    exit();
?>
