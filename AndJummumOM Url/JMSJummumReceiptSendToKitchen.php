<?php
    include_once("dbConnect.php");
    setConnectionValue("AND_JUMMUM");
    writeToLog("file: " . basename(__FILE__) . ", user: " . $_POST["modifiedUser"]);
    printAllPost();
    ini_set("memory_limit","-1");
    

    if(isset($_POST["branchID"]) && isset($_POST["receiptID"]) && isset($_POST["status"]) && isset($_POST["sendToKitchenDate"]) && isset($_POST["deliveredDate"]) && isset($_POST["modifiedUser"]) && isset($_POST["modifiedDate"]))
    {
        $branchID = $_POST["branchID"];
        $receiptID = $_POST["receiptID"];
        $status = $_POST["status"];
        $sendToKitchenDate = $_POST["sendToKitchenDate"];
        $deliveredDate = $_POST["deliveredDate"];
        $modifiedUser = $_POST["modifiedUser"];
        $modifiedDate = $_POST["modifiedDate"];
        
        
        $modifiedDeviceToken = $_POST["modifiedDeviceToken"];
        
    }
    if(isset($_POST["maxModifiedDate"]))
    {
        $maxModifiedDate = $_POST["maxModifiedDate"];
    
    }
    
    
    
    
    // Check connection
    if (mysqli_connect_errno())
    {
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
    }
    
    
    
    // Set autocommit to off
    mysqli_autocommit($con,FALSE);
    writeToLog("set auto commit to off");
    

    
    $alreadyDone = 0;
    if($status == 5)
    {
        $sql = "select * from receipt where receiptID = '$receiptID' and status = '2'";
        $selectedRow = getSelectedRow($sql);
        if(sizeof($selectedRow) == 0)
        {
            $alreadyDone = 1;
        }
        
        
        $msg = "Processing";
        $category = "processing";
    }
    else if($status == 6)
    {
        $sql = "select * from receipt where receiptID = '$receiptID' and status = '5'";
        $selectedRow = getSelectedRow($sql);
        if(sizeof($selectedRow) == 0)
        {
            $alreadyDone = 1;
        }
        
        $msg = "Delivered";
        $category = "delivered";
    }

    writeToLog("alreadyDone: " . $alreadyDone);
    if(!$alreadyDone)
    {
        if($status == 5)
        {
            $sql = "update receipt set status = '$status', statusRoute = concat(statusRoute,',','$status'),sendToKitchenDate='$sendToKitchenDate', modifiedUser = '$modifiedUser', modifiedDate = '$modifiedDate' where receiptID = '$receiptID'";
            
        }
        else if($status == 6)
        {
            $sql = "update receipt set status = '$status', statusRoute = concat(statusRoute,',','$status'),deliveredDate='$deliveredDate', modifiedUser = '$modifiedUser', modifiedDate = '$modifiedDate' where receiptID = '$receiptID'";
            
        }
        
        $ret = doQueryTask($sql);
        if($ret != "")
        {
            mysqli_rollback($con);
            //        putAlertToDevice();
            echo json_encode($ret);
            exit();
        }
    }
    
    
    
//    2,5,7,8,13
    $sql = "select receipt.* from receipt where receipt.branchID = '$branchID' and status in (2,5,7,8,13)";
    $selectedRow = getSelectedRow($sql);
    if(sizeof($selectedRow)==0)
    {
        $sql = "select UrlNoti,AlarmShop from AND_JUMMUM_OM.branch where branchID = '$branchID'";
        $selectedRow = getSelectedRow($sql);
        $urlNoti = $selectedRow[0]["UrlNoti"];
        $alarmShop = $selectedRow[0]["AlarmShop"];
        if($alarmShop == 1)
        {
            //alarmShopOff
            //query statement
            $ledStatus = 0;
            $sql = "update AND_JUMMUM_OM.Branch set LedStatus = '$ledStatus', ModifiedUser = '$modifiedUser', ModifiedDate = '$modifiedDate' where branchID = '$branchID';";
            $ret = doQueryTask($sql);
            if($ret != "")
            {
                mysqli_rollback($con);
                //        putAlertToDevice();
                echo json_encode($ret);
                exit();
            }
        }
    }
    
    
    
    
    if($status == 11)
    {
        
        //get pushSync Device in jummum
        $sql = "select * from setting where KeyName = 'DeviceTokenAdmin'";
        $selectedRow = getSelectedRow($sql);
        $pushSyncDeviceTokenAdmin = $selectedRow[0]["Value"];
        $arrPushSyncDeviceTokenAdmin = array();
        array_push($arrPushSyncDeviceTokenAdmin,$pushSyncDeviceTokenAdmin);
        sendPushNotificationToDeviceWithPath($arrPushSyncDeviceTokenAdmin,'./../AND_JUMMUM/','jill','negotiation arrive!',0,0,1);
        
        
        //alarm admin
        $sql = "select * from setting where keyName = 'AlarmAdmin'";
        $selectedRow = getSelectedRow($sql);
        $alarmAdmin = $selectedRow[0]["Value"];
        if(intval($alarmAdmin) == 1)
        {
            //alarmAdmin
            //query statement
            $ledStatus = 1;
            $sql = "update Setting set Value = '$ledStatus', ModifiedUser = '$modifiedUser', ModifiedDate = '$modifiedDate' where KeyName = 'LedStatus';";
            $ret = doQueryTask($sql);
            if($ret != "")
            {
                mysqli_rollback($con);
                //        putAlertToDevice();
                echo json_encode($ret);
                exit();
            }
        }
    }
    
    
    //dataJson
    $sql = "select '$alreadyDone' as Text;";
    $sql .= "select * from receipt where receiptID = '$receiptID';";
    $sql .= "select * from receipt where branchID = '$branchID' and modifiedDate > '$maxModifiedDate';";
    $dataJson = executeMultiQueryArray($sql);
    
    
    
    
    
    //push sync to other device
    $pushSyncDeviceTokenReceiveOrder = array();
    $sql = "select * from AND_JUMMUM_OM.device left join AND_JUMMUM_OM.Branch on AND_JUMMUM_OM.device.DbName = AND_JUMMUM_OM.Branch.DbName where branchID = '$branchID';";
    $selectedRow = getSelectedRow($sql);
    for($i=0; $i<sizeof($selectedRow); $i++)
    {
        $deviceToken = $selectedRow[$i]["DeviceToken"];
        array_push($pushSyncDeviceTokenReceiveOrder,$deviceToken);
    }    
    
    sendPushNotificationToDeviceWithPath($pushSyncDeviceTokenReceiveOrder,'./','jill',$msg,$receiptID,$category,1);
    
    
    
    
    
    //send noti to customer
    $sql = "select * from Receipt where receiptID = '$receiptID';";
    $selectedRow = getSelectedRow($sql);
    $memberID = $selectedRow[0]["MemberID"];
    
    
    $sql = "select login.DeviceToken from login left join useraccount on login.username = useraccount.username where useraccount.UserAccountID = '$memberID' order by login.modifiedDate desc limit 1;";
    $selectedRow = getSelectedRow($sql);
    $customerDeviceToken = $selectedRow[0]["DeviceToken"];
    $arrCustomerDeviceToken = array();
    array_push($arrCustomerDeviceToken,$customerDeviceToken);
    $category = "updateStatus";
    sendPushNotificationToDeviceWithPath($arrCustomerDeviceToken,'./../AND_JUMMUM/','jill',$msg,$receiptID,$category,1);
    
    
    
    
    
    
    
    
    //do script successful
    mysqli_commit($con);
    mysqli_close($con);
    
    
    
    writeToLog("query commit, file: " . basename(__FILE__) . ", user: " . $_POST['modifiedUser']);
    $response = array('status' => '1', 'sql' => $sql, 'tableName' => 'ReceiptSendToKitchen', 'dataJson' => $dataJson);
    echo json_encode($response);
    exit();
?>
