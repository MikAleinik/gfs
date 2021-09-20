<?php

require "conf.php";

date_default_timezone_set('Europe/Minsk');

$arrayURL = createURL();
downloadGribFile($arrayURL);
convertGribToGTif();
createWmsFiles();
clearOldFiles();

function createWmsFiles()
{
    require "conf.php";
    if (!file_exists($wmsFilesPath)) {
        mkdir($wmsFilesPath, 0777, false);
    }
    $hour = getHour();
    $date = "";
    if ($hour == "18") {
        $yesterday = time() - (24 * 60 * 60);
        $date = date('Ymd', $yesterday);
    } else {
        $date = date('Ymd', time());
    }
    $arrayTifFiles = "";
    $pathGeoTiff = "";
    if (file_exists($downloadPath . $date . "/" . $hour . "/gtif/")) {
        $pathGeoTiff = $downloadPath . $date . "/" . $hour . "/gtif/";
        $arrayTifFiles = scandir($pathGeoTiff);
    } else {
        for ($i = 1; $i <= 3; $i++) {
            $hour = $hour - 6;
            if ($hour >= 0) {
                if (file_exists($downloadPath . $date . "/" . $hour . "/gtif/")) {
                    $pathGeoTiff = $downloadPath . $date . "/" . $hour . "/gtif/";
                    $arrayTifFiles = scandir($pathGeoTiff);
                    break;
                }
            }
        }
    }
    if ($pathGeoTiff != "") {
        $wmsHeaderData = "MAP\r\n\tNAME METEOPORTAL\r\n\tSTATUS ON\r\n\tSIZE 768 768\r\n\tEXTENT -180 -90 180 90\r\n\tUNITS DD\r\n\tIMAGECOLOR 255 255 255\r\n";
        $wmsHeaderData = $wmsHeaderData . "PROJECTION\r\n\t\"init=epsg:4326\"\r\nEND\r\n";
        $wmsHeaderData = $wmsHeaderData . "OUTPUTFORMAT\r\n\tNAME \"aggJPEG\"\r\n\tDRIVER \"AGG/JPEG\"\r\n\tMIMETYPE \"image/jpg\"\r\n\tIMAGEMODE RGB\r\n\tEXTENSION \"jpg\"\r\nEND\r\n";
        $wmsHeaderData = $wmsHeaderData . "WEB\r\n\tMINSCALE 1000\r\n\tMAXSCALE 10000000\r\n";
        $currentHour = -1;
        for ($i = 2; $i < count($arrayTifFiles); $i++) {
            $currentHour++;
            if ($i >= 122) {
                $currentHour = $currentHour + 2;
            }
            if ($currentHour >= 24) {
                $currentHour = 0;
            }
            $coeffDate = 0;
            $numberTifFile = 0;
            if ($i < 122) {
                $coeffDate = floor(($i - 2) / 24);
                $numberTifFile = addZeroToNumber($i - 2);
            } else {
                $coeffDate = 5 + floor(($i - 122) / 8);
                $numberTifFile = 120 + ($i - 122) * 3;
            }
            $currentWmsFile = fopen($wmsFilesPath . ($date + $coeffDate) . "_" . addOneZeroToNumber($currentHour) . "_wmsmap.map", "w+");
            $wmsData = "";
            $wmsData = $wmsData . "METADATA\r\n\t\"wms_title\" \"Meteoportal\"\r\n\t\"wms_onlineresource\" \"" . $onlineUrl . "wmsmap" . ($date + $coeffDate) . "_" . addOneZeroToNumber($currentHour) . "\"\r\n\t\"wms_server_version\" \"1.3.0\"\r\n\t\"wms_srs\" \"EPSG:3395 EPSG:4326 EPSG:3857\"\r\n\t\"wms_enable_request\" \"*\"\r\n\t\"wms_encoding\" \"UTF8\"\r\nEND\r\nEND\r\n";
            $wmsData = $wmsData . "### Layers start\r\nLAYER\r\n\tPROJECTION\r\n\t\t\"init=epsg:" . $typeEPSG . "\"\r\n\tEND\r\n";
            $wmsData = $wmsData . "\tNAME \"clouding\"\r\n";
            $wmsData = $wmsData . "\tMETADATA\r\n\t\t\"wms_title\" \"gfs clouding\"\r\n\t\t\"wms_srs\" \"ESPG:" . $typeEPSG . "\"\r\n\t\t\"wms_enable_request\" \"*\"\r\n\tEND\r\n";
            $wmsData = $wmsData . "\tDATA " . $pathGeoTiff . $date . "-f" . $numberTifFile . "-c.tif\r\n";
            $wmsData = $wmsData . "\tTYPE RASTER\r\n\tCLASSITEM \"[pixel]\"\r\n\tSTATUS ON\r\n\tPROCESSING \"RESAMPLE=BILINEAR\"\r\n\tOFFSITE 0 0 0\r\n";
            $wmsData = $wmsData . "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 0 AND [pixel] <= 0)\r\n\t\tSTYLE\r\n\t\t\tCOLOR " . $color . "\r\n\t\t\tDATARANGE  0.0 0.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 0\r\n\t\tEND\r\n\tEND\r\n";
            $wmsData = $wmsData . "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 1 AND [pixel] <= 10)\r\n\t\tSTYLE\r\n\t\t\tCOLOR " . $color . "\r\n\t\t\tDATARANGE  1.0 10.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 10\r\n\t\tEND\r\n\tEND\r\n";
            $wmsData = $wmsData . "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 11 AND [pixel] <= 20)\r\n\t\tSTYLE\r\n\t\t\tCOLOR " . $color . "\r\n\t\t\tDATARANGE  11.0 20.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 20\r\n\t\tEND\r\n\tEND\r\n";
            $wmsData = $wmsData . "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 21 AND [pixel] <= 30)\r\n\t\tSTYLE\r\n\t\t\tCOLOR " . $color . "\r\n\t\t\tDATARANGE  21.0 30.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 30\r\n\t\tEND\r\n\tEND\r\n";
            $wmsData = $wmsData . "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 31 AND [pixel] <= 40)\r\n\t\tSTYLE\r\n\t\t\tCOLOR " . $color . "\r\n\t\t\tDATARANGE  31.0 40.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 40\r\n\t\tEND\r\n\tEND\r\n";
            $wmsData = $wmsData . "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 41 AND [pixel] <= 50)\r\n\t\tSTYLE\r\n\t\t\tCOLOR " . $color . "\r\n\t\t\tDATARANGE  41.0 50.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 50\r\n\t\tEND\r\n\tEND\r\n";
            $wmsData = $wmsData . "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 51 AND [pixel] <= 60)\r\n\t\tSTYLE\r\n\t\t\tCOLOR " . $color . "\r\n\t\t\tDATARANGE  51.0 60.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 60\r\n\t\tEND\r\n\tEND\r\n";
            $wmsData = $wmsData . "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 61 AND [pixel] <= 70)\r\n\t\tSTYLE\r\n\t\t\tCOLOR " . $color . "\r\n\t\t\tDATARANGE  61.0 70.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 70\r\n\t\tEND\r\n\tEND\r\n";
            $wmsData = $wmsData . "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 71 AND [pixel] <= 80)\r\n\t\tSTYLE\r\n\t\t\tCOLOR " . $color . "\r\n\t\t\tDATARANGE  71.0 80.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 80\r\n\t\tEND\r\n\tEND\r\n";
            $wmsData = $wmsData . "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 81 AND [pixel] <= 90)\r\n\t\tSTYLE\r\n\t\t\tCOLOR " . $color . "\r\n\t\t\tDATARANGE  81.0 90.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 90\r\n\t\tEND\r\n\tEND\r\n";
            $wmsData = $wmsData . "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 91 AND [pixel] <= 100)\r\n\t\tSTYLE\r\n\t\t\tCOLOR " . $color . "\r\n\t\t\tDATARANGE  91.0 100.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 100\r\n\t\tEND\r\n\tEND\r\n";
            $wmsData = $wmsData . "END #layer\r\nEND";
            $resultWrite = fwrite($currentWmsFile, $wmsHeaderData);
            $resultWrite = fwrite($currentWmsFile, $wmsData);
            if (!$resultWrite) {
                logMessage("File " . $wmsFilesPath . $date + $coeffDate . "_" . addOneZeroToNumber($currentHour) . "_wmsmap.map" . " not created.");
            }
            fclose($currentWmsFile);
        }
    } else {
        logMessage("WMS files were not updated.");
    }
}

function clearOldFiles()
{
    require "conf.php";
    $saveDay = time() - ($durationStorageFile * 24 * 60 * 60);
    $saveDate = date('Ymd', $saveDay);
    $arrayDirectory = scandir($downloadPath);
    for ($i = 2; $i < count($arrayDirectory); $i++) {
        if ($arrayDirectory[$i] < $saveDate) {
            deleteDirectory($downloadPath . $arrayDirectory[$i]);
        }
    }
}

function deleteDirectory($nameDirectory)
{
    $arrayFiles = scandir($nameDirectory);
    for ($i = 2; $i < count($arrayFiles); $i++) {
        if (is_dir($nameDirectory . "/" . $arrayFiles[$i]) == true) {
            deleteDirectory($nameDirectory . "/" . $arrayFiles[$i]);
        } else {
            unlink($nameDirectory . "/" . $arrayFiles[$i]);
        }
    }
    rmdir($nameDirectory);
}

function convertGribToGTif()
{
    require "conf.php";
    if (!file_exists($utilitePath)) {
        logMessage("Utilite directory not found.");
        return false;
    }
    $hour = getHour();
    $date = "";
    if ($hour == "18") {
        $yesterday = time() - (24 * 60 * 60);
        $date = date('Ymd', $yesterday);
    } else {
        $date = date('Ymd', time());
    }
    $pathGrib = $downloadPath . $date . "/" . $hour . "/grib/";
    $arrayGribFiles = scandir($pathGrib);
    if ($arrayGribFiles) {
        mkdir($downloadPath . $date . "/" . $hour . "/gtif/", 0777, false);
        $pathGeoTiff = $downloadPath . $date . "/" . $hour . "/gtif/";
        for ($i = 2; $i < count($arrayGribFiles); $i++) {
            $numberFile = substr($arrayGribFiles[$i], -4);
            if ($numberFile != ".txt") {
                // Для конвертации локально через консоль GDALShell.bat
                exec("gdal_translate -a_srs EPSG:3857 -ot Byte -of Gtiff " . $pathGrib . $arrayGribFiles[$i] . " " . $pathGeoTiff . $date . "-" . $numberFile . ".tif", $resultCode);
                //exec("gdalwarp -s_srs EPSG:4326 -t_srs EPSG:" . $typeEPSG . " " . $pathGeoTiff . $date . "-" . $numberFile . ".tif" . " " . $pathGeoTiff . $date . "-" . $numberFile . "-c.tif", $resultCode);
                //$resultDelete = unlink($pathGeoTiff . $date . "-" . $numberFile . ".tif");
                // Для конвертации на сервере
                // exec($utilitePath . "gdal_translate -a_srs " . $typeWGS . " -ot Byte -of Gtiff " . $pathGrib . $arrayGribFiles[$i] . " " . $pathGeoTiff . $date . "-" . $numberFile . ".tif", $resultCode);
                // exec($utilitePath . "gdalwarp -s_srs EPSG:4326 -t_srs EPSG:" . $typeEPSG . " " . $pathGeoTiff . $date . "-" . $numberFile . ".tif" . " " . $pathGeoTiff . $date . "-" . $numberFile . "-c.tif", $resultCode);
                // $resultDelete = unlink($pathGeoTiff . $date . "-" . $numberFile . ".tif");
                if (!$resultCode) {
                    logMessage("File " . $arrayGribFiles[$i] . " not converted to GTiff.");
                }
                if (!$resultDelete) {
                    logMessage("File " . $arrayGribFiles[$i] . " not deleted.");
                }
            }
        }
    } else {
        logMessage("No GRIB files found for conversion to GTiff.");
    }
}

function downloadGribFile($arrayURL)
{
    require "conf.php";
    if (!file_exists($downloadPath)) {
        mkdir($downloadPath, 0777, false);
    }
    $hour = getHour();
    $date = "";
    if ($hour == "18") {
        $yesterday = time() - (24 * 60 * 60);
        $date = date('Ymd', $yesterday);
    } else {
        $date = date('Ymd', time());
    }
    if (!file_exists($downloadPath) . $date) {
        mkdir($downloadPath . $date, 0777, false);
    }
    mkdir($downloadPath . $date . "/" . $hour, 0777, false);
    mkdir($downloadPath . $date . "/" . $hour . "/grib/", 0777, false);
    $downloadPathGrib = $downloadPath . $date . "/" . $hour . "/grib/";
    if ($durationForecast < 120) {
        for ($i = 0; $i < $durationForecast; $i++) {
            $numberFile = substr($arrayURL[$i], 85, 4);
            $resultDownload = file_put_contents($downloadPathGrib . $date . "." . $numberFile, file_get_contents($arrayURL[$i]));
            if (!$resultDownload) {
                logMessage($downloadPathGrib . $date . "." . $numberFile . " file not loaded.");
            }
        }
    } else {
        for ($i = 0; $i < 120; $i++) {
            $numberFile = substr($arrayURL[$i], 85, 4);
            $resultDownload = file_put_contents($downloadPathGrib . $date . "." . $numberFile, file_get_contents($arrayURL[$i]));
            if (!$resultDownload) {
                logMessage($downloadPathGrib . $date . "." . $numberFile . " file not loaded.");
            }
        }
        for ($i = 120; $i < $durationForecast; $i = $i + 3) {
            $numberFile = substr($arrayURL[$i], 85, 4);
            $resultDownload = file_put_contents($downloadPathGrib . $date . "." . $numberFile, file_get_contents($arrayURL[$i]));
            if (!$resultDownload) {
                logMessage($downloadPathGrib . $date . "." . $numberFile . " file not loaded.");
            }
        }
    }
    $infoFile = fopen($downloadPathGrib . "loaded_layers.txt", "w+");
    foreach ($layers as $key => $value) {
        if ($value == "on") {
            fwrite($infoFile, $key . "\n");
        }
    }
    fclose($infoFile);
}

function createURL()
{
    require "conf.php";
    $url = 'https://nomads.ncep.noaa.gov/cgi-bin/filter_gfs_0p25_1hr.pl?';
    $hour = getHour();
    $file = 'file=gfs.t' . $hour . 'z.pgrb2.0p25.f';
    $listLayers = "";
    foreach ($layers as $key => $value) {
        if ($value == "on") {
            $listLayers = $listLayers . "&" . $key . "=" . $value;
        }
    }
    $listCoordinate = "";
    foreach ($coordinate as $key => $value) {
        $listCoordinate = $listCoordinate . "&" . $key . "=" . $value;
    }
    $date = "";
    if ($hour == "18") {
        $yesterday = time() - (24 * 60 * 60);
        $date = date('Ymd', $yesterday);
    } else {
        $date = date('Ymd', time());
    }
    $dir = '&dir=%2Fgfs.' . $date . '%2F' . $hour . '%2Fatmos';
    $arrayURL = [];
    if ($durationForecast > 384) {
        $durationForecast = 384;
    }
    if ($durationForecast < 0) {
        $durationForecast = 0;
    }
    if ($durationForecast <= 120) {
        for ($i = 0; $i < $durationForecast; $i++) {
            $numberFile = addZeroToNumber($i);
            $arrayURL[$i] = $url . $file . $numberFile . $listLayers . $listCoordinate . $dir;
        }
    } else {
        for ($i = 0; $i <= 120; $i++) {
            $numberFile = addZeroToNumber($i);
            $arrayURL[$i] = $url . $file . $numberFile . $listLayers . $listCoordinate . $dir;
        }
        for ($i = 123; $i < $durationForecast; $i = $i + 3) {
            $numberFile = addZeroToNumber($i);
            $arrayURL[$i] = $url . $file . $numberFile . $listLayers . $listCoordinate . $dir;
        }
    }
    return $arrayURL;
}

//Задержка публикации данных на GFS Forecasts составляет 6 часов
function getHour()
{
    $currentHour = floor(date('H', time()) / 6);
    $hour = "";
    switch ($currentHour) {
        case "0": {
                $hour = "18";
                // $hour = "00";
                break;
            }
        case "1": {
                $hour = "00";
                // $hour = "06";
                break;
            }
        case "2": {
                $hour = "06";
                // $hour = "12";
                break;
            }
        case "3": {
                $hour = "12";
                // $hour = "18";
                break;
            }
    }
    return $hour;
}

function addOneZeroToNumber($number)
{
    if ($number < 10) {
        return "0" . $number;
    }
    return $number;
}

function addZeroToNumber($number)
{
    if ($number < 10) {
        return "00" . $number;
    } elseif ($number < 100) {
        return "0" . $number;
    }
    return $number;
}

function logMessage($message)
{
    $logErrorFile = fopen(__DIR__ . "/error.log", "a");
    fwrite($logErrorFile, "\r\n[" . date('d.m.Y H:i:s', time()) . "] " . $message);
    fclose($logErrorFile);
}   

function printURL($arrayURL)
{
    foreach ($arrayURL as $element) {
        echo $element . "<br>";
    }
}

//Пример строки запроса загрузки на 00 часов 2021.08.29 с прогнозом на 24 часа
//$url = 'https://nomads.ncep.noaa.gov/cgi-bin/filter_gfs_0p25_1hr.pl?file=gfs.t00z.pgrb2.0p25.f023&lev_convective_cloud_layer=on&leftlon=0&rightlon=360&toplat=90&bottomlat=-90&dir=%2Fgfs.20210829%2F00%2Fatmos';

//Пример строки для конвертации в GeoTIFF с привязкой координат к WGS84
//exec("gdal_translate -a_srs WGS84 -ot Byte -of Gtiff c:\\Localhost\\Cloud\\gfs\\download\\20210831-06.f000 c:\\Localhost\\Cloud\\gfs\\download\\20210831-06-f000.tif");

//Пример строки для конвертации в проекцию ESPG 3857
//exec("gdalwarp -s_srs EPSG:4326 -t_srs EPSG:3857 c:\\Localhost\\Cloud\\gfs\\download\\20210831-06-f000.tif c:\\Localhost\\Cloud\\gfs\\download\\20210831-06-f000-merc.tif");
