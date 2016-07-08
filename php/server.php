<?php
/*
  ~ Copyright (c) 2016 Lars Gaidzik & Lukas Mahr & Victor Dienstbier
  ~ This program is free software: you can redistribute it and/or modify
  ~ it under the terms of the GNU General Public License as published by
  ~ the Free Software Foundation, either version 3 of the License, or
  ~ (at your option) any later version.
  ~
  ~ This program is distributed in the hope that it will be useful,
  ~ but WITHOUT ANY WARRANTY; without even the implied warranty of
  ~ MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  ~ GNU General Public License for more details.
  ~
  ~ You should have received a copy of the GNU General Public License
  ~ along with this program.  If not, see <http://www.gnu.org/licenses/>.
  */

//include './mensa.php';        //Mensa deaktiviert!
include './classes.php';
const __VERSIONNUMBER = 3.1;




$server = new SoapServer(
        null, array(                    //Parameter	Bedeutung                       Priorität
    'uri' => "http://localhost",        //uri           Namespace des SOAP-Service	notwendig wenn WSDL nicht genutzt wird            
    'encoding' => 'UTF-8',              //encoding	Zeichensatz für SOAP-Request	optional
    'soap_version' => SOAP_1_2          //soap_version	Eingesetzte Protokollversion	optional
        )
);

function addGeneralInfos($nameOfContent, $arrContent){
    return array(
        "version" => __VERSIONNUMBER,
        $nameOfContent => $arrContent
            );
}

/**
 * 
 * @return type Speiesplan, der aus der XML-Datei erzeugt wird.
 */
/*
 * Mensa deaktiviert!
function getMenu(){
    return addGeneralInfos("menu", readMenuXML());
}
 * 
 */

/** 
 * @return type Array aller Studiengänge eines Semesterhalbjahres
 */
function getCourses($tt){    
    require 'connect_db.php';   
    
    $param_select = array(
        "sg.Bezeichnung",
        "sg.Bezeichnung_en",
        "sg.STGNR",
        "sp.Fachsemester",
        "sp.Jahr");
    $param_where = array("(sp.WS_SS=:tt)");
    $param_orderby=array("sg.Bezeichnung");
    
    $sql = "SELECT DISTINCT ".implode(' , ', $param_select).
            " FROM Stundenplan_WWW AS sp INNER JOIN Studiengaenge  AS sg ON sg.STGNR = sp.STGNR "
            . " WHERE ".implode(' AND ', $param_where).
            " ORDER BY ".implode(' , ', $param_orderby);

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':tt', $tt);
    $stmt->execute();
    
    $result = array();
    $arrSemester = array();
    while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {      
        if(!array_key_exists($row['STGNR'], $arrSemester)){
            $result[]=new Course($row["STGNR"], $row["Jahr"],$row["Bezeichnung"], $row["Bezeichnung_en"]);
        }
        $arrSemester[$row['STGNR']][]=$row["Fachsemester"];
    }
    foreach ($result as $course) {
        $semester = $arrSemester[$course->course];
        sort($semester ,SORT_NUMERIC );
        $course->addSemester($semester);
    }
    $pdo = null;
    return addGeneralInfos("courses", $result);
}

/**
 * 
 * @param type $stgnr =course(Studiengangnummer/STGNR)
 * @param type $semester =semester
 * @param type $tt =semesterhalbjahr (SS/WS)
 * @param type $id =Array aller ID's auf die gefiltert werden soll. (optional)
 * @return type Array über alle Vorlesungen eines Studienganges in einem Semester. Die Einträge sind nach Wochentag und Startzeitpunkt sortiert.
 */
function getSchedule($stgnr, $semester, $tt, $id){
    $result = array();
    if(!empty($stgnr) && !empty($semester) && !empty($tt)){
    require 'connect_db.php';
    $param_select = array(
        "sp.id",
        "sp.Bezeichnung label",
        "IF (sp.Anzeigen_int=0 , sp.InternetName, '') docent",
        "sp.VArt type",
        "sp.Gruppe 'group'",
        "DATE_FORMAT(sp.AnfDatum, '%H:%i') starttime",
        "DATE_FORMAT(sp.Enddatum, '%H:%i') endtime",
        "DATE_FORMAT(sp.AnfDatum, '%d.%m.%Y') startdate",
        "DATE_FORMAT(sp.Enddatum, '%d.%m.%Y') enddate",
        "sp.Tag_lang day",
        "sp.RaumNr room",
        "sp.SplusName splusname");
    $param_where = array("(sg.STGNR = :stgnr)","(sp.Fachsemester = :semester)", "(sp.WS_SS = :tt)");
    $param_orderby=array("sp.Tag_Nr", "starttime");
    
    if(!empty($id)){
        array_push($param_where, "sp.id IN (".implode(",",$id).")");
    }
    $sql = "SELECT ".implode(' , ', $param_select).
            " FROM Stundenplan_WWW AS sp JOIN Studiengaenge AS sg ON sg.STGNR = sp.STGNR "
            . " WHERE ".implode(' AND ', $param_where).
            " ORDER BY ".implode(' , ', $param_orderby);
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':stgnr', $stgnr);
    $stmt->bindParam(':semester', $semester);
    $stmt->bindParam(':tt', $tt);    
    $stmt->execute();
    while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
        if($row['starttime']!= null && $row['endtime']!=null){
            $result[] = $row;
        }
    }
    $pdo = null;
    }
    return addGeneralInfos("schedule", $result);
}

/**
 *  
 * @param type $id =Array aller Stundenplan_WWW ID's 
 * @return type Array über alle Vorlesungen dessen ID's enthalten sind. Die Einträge sind nach Wochentag und Startzeitpunkt sortiert.
 */
function getMySchedule($id){
    $result = array();
    if(!empty($id)){
    require 'connect_db.php';
    $param_select = array(
        "sp.id",
        "sp.Bezeichnung label",
        "IF (sp.Anzeigen_int=0 , sp.InternetName, '') docent",
        "sp.VArt type",
        "sp.Gruppe 'group'",
        "DATE_FORMAT(sp.AnfDatum, '%H:%i') starttime",
        "DATE_FORMAT(sp.Enddatum, '%H:%i') endtime",
        "DATE_FORMAT(sp.AnfDatum, '%d.%m.%Y') startdate",
        "DATE_FORMAT(sp.Enddatum, '%d.%m.%Y') enddate",
        "sp.Tag_lang day",
        "sp.RaumNr room",
        "sp.SplusName splusname");
    $param_where = array( "sp.id IN (".implode(",",$id).")");    
    $param_orderby=array("sp.Tag_Nr", "starttime");

    
    $sql = "SELECT ".implode(' , ', $param_select).
            " FROM Stundenplan_WWW as sp "
            . " WHERE ".implode(' AND ', $param_where).
            " ORDER BY ".implode(' , ', $param_orderby);
    $stmt = $pdo->prepare($sql);  
    $stmt->execute();
    while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
        if($row['starttime']!= null && $row['endtime']!=null){
            $result[] = $row;
        }
    }
    $pdo = null;
    }    
    return addGeneralInfos("myschedule", $result);
}

/**
 * 
 * @param type $stgnr =studiengang
 * @param type $semester =semesterjahr
 * @param type $tt =semesterhalbjahr (SS/WS)
 * @param type $id =Array aller ID's die gesucht werden sollen
 * @return type Array das immer den Stundenplan der aktuellen Wochen mit den in dieser Woche fälligen Änderungen anzeigt.
 */
function getMergedSchedule($stgnr, $semester, $tt, $id) {
    $result = array();
    if(!empty($stgnr) && !empty($semester) && !empty($tt)){
    require 'connect_db.php';
 $param_select = array(
        "sp.id",
        "sp.Bezeichnung label",
        "IF (sp.Anzeigen_int=0 , sp.InternetName, '') docent",
        "sp.VArt type",
        "sp.Gruppe 'group'",
        "DATE_FORMAT(sp.AnfDatum, '%H:%i') starttime",
        "DATE_FORMAT(sp.Enddatum, '%H:%i') endtime",
        "DATE_FORMAT(sp.AnfDatum, '%d.%m.%Y') startdate",
        "DATE_FORMAT(sp.Enddatum, '%d.%m.%Y') enddate",
        "sp.Tag_lang day",
        "sp.RaumNr room");
    $param_where = array("(sg.STGNR = :stgnr)","(sp.Fachsemester = :semester)", "(sp.WS_SS = :tt)");
    $param_orderby=array("sp.Tag_Nr", "starttime");     
    if(!empty($id)){
        array_push($param_where, "sp.id IN (".implode(",",$id).")");
    }
    $sql = "SELECT ".implode(' , ', $param_select).
            " FROM Stundenplan_WWW AS sp INNER JOIN Studiengaenge AS sg ON sg.STGNR = sp.STGNR "
            . " WHERE ".implode(' AND ', $param_where).
            " ORDER BY ".implode(' , ', $param_orderby); 
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':stgnr', $stgnr);
    $stmt->bindParam(':semester', $semester);
    $stmt->bindParam(':tt', $tt);
    $stmt->execute();
    $arrMSchedule = array();
    while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
        if($row['starttime'] != null && $row['endtime'] != null){
            $arrMSchedule[$row['id']] = new MSchedule($row['label'], $row['docent'], $row['type'], $row['group'], $row['starttime'], $row['endtime'], $row['startdate'], $row['enddate'], $row['day'], $row['room']);
        }
    }
    $stmt=null;
    $param_select=null;
    $param_where=null;
    $param_orderby=null;
    
    $param_select = array(
        "s.id",
        "v.Kommentar",
        "v.Ausfallgrund",
        "s.VArt type",
        "s.Gruppe 'group'",
        "DATE_FORMAT(v.Ersatzdatum, '%H:%i') ersatzzeit",
        "DATE_FORMAT(v.Ersatzdatum, '%d.%m.%Y') ersatzdatum",
        "v.Raum",
        "v.Ersatztag");
    $param_where = array("(s.STGNR=:stgnr)","(s.Fachsemester=:semester)","(WEEKOFYEAR(v.Ausfalldatum)=WEEKOFYEAR(NOW()) OR WEEKOFYEAR(v.Ersatzdatum)=WEEKOFYEAR(NOW()))","s.STGNR=v.STGNR");    
    
    $sqlChanges = "SELECT ".implode(" , ", $param_select)
            ." FROM stundenplan_www as s INNER JOIN Verlegungen_WWW as v ON SUBSTRING_INDEX(s.SplusName, '$', '1')=SUBSTRING_INDEX(v.SplusVerlegungsname,'$','1') "
            . "WHERE ".implode(" AND ", $param_where);
    $stmt = $pdo->prepare($sqlChanges);
    $stmt->bindParam(':stgnr', $stgnr);
    $stmt->bindParam(':semester', $semester);
    $stmt->execute();
    $result = array();
    while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
        if(array_key_exists($row['id'], $arrMSchedule)){
            $arrMSchedule[$row['id']]->setChanges(new MChanges($row['Kommentar'], $row['Ausfallgrund'], $row['ersatzzeit'], $row['ersatzdatum'], $row['Raum'], $row['Ersatztag']));            
        }
    }
    foreach ($arrMSchedule as $mSchedule) {
        $result[] = $mSchedule;
    }
    $pdo = null;
    }
    return addGeneralInfos("mschedule", $result);    
}

/**
 * 
 * @param type $stgnr =course(Studiengangnummer/STGNR)
 * @param type $semester =semester
 * @param type $tt =semesterhalbjahr (SS/WS)
 * @param type $id =Array aller ID's die gesucht werden sollen
 * @return type Array über alle Änderungen eines Studienganges in einem Semester. Die Einträge sind nach Ausfalltag und Ausfallzeitpunkt sortiert.
 */
function getChanges($stgnr, $semester, $tt, $id) {
    $result = array();
    if(!empty($stgnr) && !empty($semester) && !empty($tt)){
    require 'connect_db.php';
    
     $param_select = array(
         "v.id",
        "v.Bezeichnung bezeichnung",
        "IF (v.Anzeigen_int=0, v.InternetName, '') dozent",
        "v.Tag_lang ausfalltag",
        "v.Kommentar kommentar",
        "v.Gruppe gruppe",
        "DATE_FORMAT(v.Ausfalldatum, '%H:%i') ausfallzeit",
        "DATE_FORMAT(v.Ausfalldatum, '%d.%m.%Y') ausfalldatum",
        "v.RaumNr ausfallraum",
        "v.Ausfallgrund ausfallgrund",
        "DATE_FORMAT(v.Ersatzdatum, '%H:%i') ersatzzeit",
        "DATE_FORMAT(v.Ersatzdatum, '%d.%m.%Y') ersatzdatum",
         "v.Raum ersatzraum",
         "v.Ersatztag ersatztag",
         "v.SplusVerlegungsname splusname");
    $param_where = array("(v.STGNR = :stgnr)","(s.STGNR = :stgnr)","(v.Fachsemester = :semester)","(DAYOFYEAR(DATE(v.Ausfalldatum))>=DAYOFYEAR(NOW()) OR DAYOFYEAR(DATE(v.Ersatzdatum))>=DAYOFYEAR(NOW()))", "(s.WS_SS = :tt)");
    $param_orderby=array("ausfalldatum", "ausfallzeit");
    if(!empty($id)){
        array_push($param_where, "s.id IN (".implode(",",$id).")");
    }
    
    $sql = "SELECT ".implode(", ", $param_select)
            ." FROM Stundenplan_WWW as s INNER JOIN Verlegungen_WWW as v ON SUBSTRING_INDEX(s.SplusName, '$', '1')=SUBSTRING_INDEX(v.SplusVerlegungsname,'$','1')"
            . " WHERE ".implode(" AND ", $param_where)
            ." ORDER BY ".implode(", ",$param_orderby);
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':stgnr', $stgnr);
    $stmt->bindParam(':semester', $semester);
    $stmt->bindParam(':tt', $tt);
    $stmt->execute();    
    while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
        if($row['ausfallzeit']!= null){
        $result[] = array(
            "id"=>$row['id'],
            "label"=>$row['bezeichnung'], 
            "docent" => $row['dozent'],             
            "comment"=>$row['kommentar'], 
            "reason" => $row['ausfallgrund'],
            "group" => $row['gruppe'],
            "splusname" => $row['splusname'],
            "original" => array(
                "day" => $row['ausfalltag'], 
                "time" => $row['ausfallzeit'], 
                "date" => $row['ausfalldatum'], 
                "room" => $row['ausfallraum']), 
            "alternative"=> ($row['ersatztag']!=null && $row['ersatzzeit']!= null) ? array(
                "day" => $row['ersatztag'], 
                "time" => $row['ersatzzeit'], 
                "date" => $row['ersatzdatum'], 
                "room" => $row['ersatzraum']): null,            
            );
        }
    }
    // Datenbankverbindung schließen
    $pdo = null;
    }   
    return addGeneralInfos("changes", $result);
}

//$server->addFunction("getMenu");
$server->addFunction("getSchedule");
$server->addFunction("getMySchedule");
$server->addFunction("getMergedSchedule");
$server->addFunction("getCourses");
$server->addFunction("getChanges");

$server->handle();