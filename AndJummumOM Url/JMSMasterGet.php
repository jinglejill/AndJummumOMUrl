<?php
    include_once("dbConnect.php");
    setConnectionValue($_POST["dbName"]);
    writeToLog("file: " . basename(__FILE__) . ", user: " . $_POST["modifiedUser"]);
    printAllPost();
    ini_set("memory_limit","-1");
    $dbName = $_POST["dbName"];
    
    
    //test git
    
    
    // Check connection
    if (mysqli_connect_errno())
    {
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
    }
    
    
    
    $sql = "select * from AND_JUMMUM_OM.branch where dbName = '$dbName'";
    $selectedRow = getSelectedRow($sql);
    $branchID = $selectedRow[0]["BranchID"];
    

    
    
    //build sql statement for table
    $sql = "select * from setting union select SettingID+1000, `KeyName`, `Value`,Type, Remark, `ModifiedUser`, `ModifiedDate` from AND_JUMMUM_OM.setting where type = 2;";
    $sql .= "select * from customerTable;";
    $sql .= "select * from menuType;";
    $sql .= "select * from menu;";
    $sql .= "select * from noteType;";
    $sql .= "select * from note;";
    
    
    //****-----
    $sql2 = "(select AND_JUMMUM.receipt.* from AND_JUMMUM.receipt where AND_JUMMUM.receipt.branchID = '$branchID' and status in (2,5,7,8,11,12,13)) UNION (select AND_JUMMUM.receipt.* from AND_JUMMUM.receipt where branchID = '$branchID' and status = '6' order by receipt.ReceiptDate DESC, receipt.ReceiptID DESC limit 20) UNION (select AND_JUMMUM.receipt.* from AND_JUMMUM.receipt where branchID = '$branchID' and status in (9,10,14) order by receipt.ReceiptDate DESC, receipt.ReceiptID DESC limit 20);";
    $selectedRow = getSelectedRow($sql2);
    
    
    $receiptIDList = array();
    for($i=0; $i<sizeof($selectedRow); $i++)
    {
        array_push($receiptIDList,$selectedRow[$i]["ReceiptID"]);
    }
    if(sizeof($receiptIDList) > 0)
    {
        $receiptIDListInText = $receiptIDList[0];
        for($i=1; $i<sizeof($receiptIDList); $i++)
        {
            $receiptIDListInText .= "," . $receiptIDList[$i];
        }
        
        
        $sql2 .= "select * from AND_JUMMUM.OrderTaking where receiptID in ($receiptIDListInText);";
        $sql2 .= "select * from AND_JUMMUM.OrderNote where orderTakingID in (select orderTakingID from AND_JUMMUM.OrderTaking where receiptID in ($receiptIDListInText));";
        $sql2 .= "select * from AND_JUMMUM.Dispute where receiptID in ($receiptIDListInText);";
    }
    else
    {
        $sql2 .= "select * from AND_JUMMUM.OrderTaking where 0;";
        $sql2 .= "select * from AND_JUMMUM.OrderNote where 0;";
        $sql2 .= "select * from AND_JUMMUM.Dispute where 0;";        
    }
    $sql .= $sql2;
    //****-----
    
    
    $sql .= "select * from AND_JUMMUM.DisputeReason where status = 1;";
    
    
    
    
    /* execute multi query */
    $jsonEncode = executeMultiQuery($sql);
    echo $jsonEncode;


    
    // Close connections
    mysqli_close($con);
    
?>
