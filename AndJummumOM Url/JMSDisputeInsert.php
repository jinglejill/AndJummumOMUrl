<?php
    include_once("dbConnect.php");
    setConnectionValue("AND_JUMMUM");
    writeToLog("file: " . basename(__FILE__) . ", user: " . $_POST["modifiedUser"]);
    printAllPost();
    
    

    if(isset($_POST["branchID"]))
    {
        $branchID = $_POST["branchID"];
    }
    if(isset($_POST["disputeID"]) && isset($_POST["receiptID"]) && isset($_POST["disputeReasonID"]) && isset($_POST["refundAmount"]) && isset($_POST["detail"]) && isset($_POST["phoneNo"]) && isset($_POST["type"]) && isset($_POST["modifiedUser"]) && isset($_POST["modifiedDate"]))
    {
        $disputeID = $_POST["disputeID"];
        $receiptID = $_POST["receiptID"];
        $disputeReasonID = $_POST["disputeReasonID"];
        $refundAmount = $_POST["refundAmount"];
        $detail = $_POST["detail"];
        $phoneNo = $_POST["phoneNo"];
        $type = $_POST["type"];
        $modifiedUser = $_POST["modifiedUser"];
        $modifiedDate = $_POST["modifiedDate"];
        
        
        $modifiedDeviceToken = $_POST["modifiedDeviceToken"];
    }

    
    
    
    // Check connection
    if (mysqli_connect_errno())
    {
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
    }
    
    
    
    // Set autocommit to off
    mysqli_autocommit($con,FALSE);
    writeToLog("set auto commit to off");
    
    
    
    
    //dispute
    //query statement
    $sql = "INSERT INTO Dispute(ReceiptID, DisputeReasonID, RefundAmount, Detail, PhoneNo, Type, ModifiedUser, ModifiedDate) VALUES ('$receiptID', '$disputeReasonID', '$refundAmount', '$detail', '$phoneNo', '$type', '$modifiedUser', '$modifiedDate')";
    $ret = doQueryTask($sql);
    $disputeID = mysqli_insert_id($con);
    if($ret != "")
    {
        mysqli_rollback($con);
//        putAlertToDevice();
        echo json_encode($ret);
        exit();
    }
    
    
    
    switch($type)
    {
        case 3:
        {
            $status = 9;//confirm cancel
        }
            break;
        case 4:
        {
            $status = 10;//confirm dispute
        }
            break;
    }
    
    //receipt
    $sql = "update receipt set status = '$status',statusRoute=concat(statusRoute,',','$status'), modifiedUser = '$modifiedUser', modifiedDate = '$modifiedDate' where receiptID = '$receiptID'";
    $ret = doQueryTask($sql);
    if($ret != "")
    {
        mysqli_rollback($con);
//        putAlertToDevice();
        echo json_encode($ret);
        exit();
    }
    
    
    
    

    
    //    2,5,7,8,13
    $sql = "select receipt.* from receipt where receipt.branchID = '$branchID' and status in (2,5,7,8,13)";
    $selectedRow = getSelectedRow($sql);
    if(sizeof($selectedRow)==0)
    {
        $sql = "select UrlNoti,AlarmShop from AND_JUMMUM_OM.branch where branchID = '$branchID'";
        $selectedRow = getSelectedRow($sql);
//        $urlNoti = $selectedRow[0]["UrlNoti"];
//        $alarmShop = $selectedRow[0]["AlarmShop"];
//        if($alarmShop == 1)
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
    
    
    
    
    
    
    //do script successful
    mysqli_commit($con);


    /* execute multi query */
    $sql = "select * from Receipt where receiptID = '$receiptID';";
    $sql .= "select * from Dispute where receiptID = '$receiptID' and disputeID = '$disputeID'";
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
    
    $msg = "Order dispute finished";
    $category = "clear";
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
    
    
    
    
    
    mysqli_close($con);
    writeToLog("query commit, file: " . basename(__FILE__) . ", user: " . $_POST['modifiedUser']);
    $response = array('status' => '1', 'sql' => $sql, 'tableName' => 'Receipt', dataJson => $dataJson);
    echo json_encode($response);
    exit();
?>
