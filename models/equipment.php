<?php

require_once __DIR__ . "/../config/db.php";
$equipment = new Equipment($conn);


if (isset($_GET['delete_id'])) {
    $equipment->removeEquipment($_GET['delete_id']);
    header("location: ../equipments-management.php");
    exit();
}



if (
    isset($_POST['eq_name']) &&
    isset($_POST['eq_desc']) &&
    isset($_POST['eq_qual']) &&
    isset($_POST['eq_stat'])
) {
    $name =  $_POST['eq_name'];
    $desc =  $_POST['eq_desc'];
    $qual =  $_POST['eq_qual'];
    $stat =  $_POST['eq_stat'];
    if (isset($_POST['eq_id']) && !empty($_POST['eq_id'])) {
        $equipment->updateEquipment($_POST['eq_id'], $qual, $name, $desc, $stat);
    } else {
        $equipment->addEquipment($qual, $name, $desc, $stat);
    }
    header("Location: " . dirname($_SERVER['PHP_SELF']) . "/equipments-management.php");

    exit();
}



class Equipment
{
    private $conn;
    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getAll()
    {
        $sql = "SELECT * FROM equipments";
        return $this->conn->query($sql);
    }
    public function addEquipment($eqQual, $eqName, $eqDesc, $eqStat)
    {
        $sql = "INSERT INTO equipments (eqName, eqDescription, eqQualifications, status)
        VALUES ('$eqName', '$eqDesc', '$eqQual', '$eqStat')";
        return $this->conn->query($sql);
    }

    public function removeEquipment($eqID)
    {
        $sql = "delete from equipments where eqID = '$eqID'";
        return $this->conn->query($sql);
    }

    public function updateEquipment($eqID, $eqQual, $eqName, $eqDesc, $eqStat)
    {
        $sql = "UPDATE equipments SET
                eqName = '$eqName',
                eqDescription = '$eqDesc',
                eqQualifications = '$eqQual',
                status = '$eqStat'
                WHERE eqID = '$eqID'";
        return $this->conn->query($sql);
    }
}
