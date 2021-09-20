#!/bin/bash
#Каталоги
path="/var/www/html/cloud/";#Каталог размещения модуля
utilitePath="/bin/";#Каталог размещения утилит GDAL
onlineUrl="http://192.168.1.60/cloud/";#Адрес онлайн ресурса
downloadPath=$path"download/";#Каталог размещения скачиваемых файлов
wmsFilesPath=$path"wms/";#Каталог размещения файлов конфигурации карт

#Список слоев для загрузки
declare -A layers;
layers["lev_boundary_layer_cloud_layer"]="off";
layers["lev_cloud_ceiling"]="off";
layers["lev_convective_cloud_bottom_level"]="off";
layers["lev_convective_cloud_layer"]="off";
layers["lev_convective_cloud_top_level"]="off";
layers["lev_entire_atmosphere"]="on";
layers["var_TCDC"]="on";
layers["lev_high_cloud_bottom_level"]="off";
layers["lev_high_cloud_layer"]="off";
layers["lev_high_cloud_top_level"]="off";
layers["lev_low_cloud_bottom_level"]="off";
layers["lev_low_cloud_layer"]="off";
layers["lev_low_cloud_top_level"]="off";
layers["lev_middle_cloud_bottom_level"]="off";
layers["lev_middle_cloud_layer"]="off";
layers["lev_middle_cloud_top_level"]="off";
layers["lev_planetary_boundary_layer"]="off";

#Координаты области для загрузки
declare -A coordinates;
coordinates["leftlon"]=0;
coordinates["rightlon"]=360;
coordinates["toplat"]=90;
coordinates["bottomlat"]=-90;

#Система геодезических координат
typeWGS="WGS84";
typeEPSG="3857";

#Количество часов прогноза (1 - 384)
durationForecast=1;

#Длительность хранения файлов в днях
durationStorageFile=3;

#Цвет отображения облачности
color="211 211 211";

function getHour(){
   currentHour=$(date +"%k");
   let "currentHour = currentHour / 6";
   case $currentHour in
	0)
    hour="18";;
	1)
    hour="00";;
	2)
    hour="06";;
	3)
    hour="12";;
   esac
   echo $hour;
}

function addOneZeroToNumber(){
    if [ $1 -lt 10 ]
    then
	echo "0"$1;
    else
	echo $1
    fi
}

function addZeroToNumber(){
    if [ $1 -lt 10 ]
    then
	echo "00"$1;
    elif [ $1 -lt 100 ]
    then
	echo "0"$1;
    else
	echo $1;
    fi
}

function logMessage(){
    currentTime=$(date +"%d-%m-%Y %T");
    echo "["$currentTime"] "$1 >> $path"error.log";
}

#Создание списка ссылок
url="https://nomads.ncep.noaa.gov/cgi-bin/filter_gfs_0p25_1hr.pl?";
hour=$(getHour);
file="file=gfs.t"$hour"z.pgrb2.0p25.f";
for layer in ${!layers[@]}; do
    key=${layer};
    value=${layers[${layer}]};
    if [ $value = "on" ]
    then
	listLayers+="&"$key"="$value;
    fi
done
for coordinate in ${!coordinates[@]}; do
    key=${coordinate};
    value=${coordinates[${coordinate}]};
    listCoordinate+="&"$key"="$value;
done
if [ ${coordinates["leftlon"]} -ge ${coordinates["rightlon"]} ]
then
    logMessage "Bad value longitude. Data result may be wrong.";
fi
if [ ${coordinates["bottomlat"]} -ge ${coordinates["toplat"]} ]
then
    logMessage "Bad value latitude. Data result may be wrong.";
fi
if [ $hour -eq 18 ]
then
    yesterday=$(date +"%Y%m%d");
    let "date=yesterday-1";
else
    date=$(date +"%Y%m%d");
fi
dir="&dir=%2Fgfs."$date"%2F"$hour"%2Fatmos";
if [ $durationForecast -gt 384 ]
then
    let "durationForecast=384";
    logMessage "Bad max value duration forecast. Reduced to 384.";
fi
if [ $durationForecast -lt 0 ]
then
    let "durationForecast=0";
    logMessage "Bad min value duration forecast. Increased to 0.";
fi
if [ $durationForecast -le 120 ]
then
    for ((i=0; i<$durationForecast; i++)) do
	numberFile=$(addZeroToNumber $i);
	arrayURL[$i]=$url$file$numberFile$listLayers$listCoordinate$dir;
    done
else
    for ((i=0; i<=120; i++)) do
	numberFile=$(addZeroToNumber $i);
	arrayURL[$i]=$url$file$numberFile$listLayers$listCoordinate$dir;
    done
    for ((i=123; i<=$durationForecast; i=i+3)) do
	numberFile=$(addZeroToNumber $i);
	arrayURL[$i]=$url$file$numberFile$listLayers$listCoordinate$dir;
    done
fi

#Загрузка GRIB файлов
if ! [ -d $downloadPath ]
then
    mkdir $downloadPath;
fi
if ! [ -d $downloadPath$date ]
then
    mkdir $downloadPath$date;
fi
if ! [ -d $downloadPath$date"/"$hour"/" ]
then
    mkdir $downloadPath$date"/"$hour"/";
fi
if ! [ -d $downloadPath$date"/"$hour"/grib/" ]
then
    mkdir $downloadPath$date"/"$hour"/grib/";
fi
downloadPathGrib=$downloadPath$date"/"$hour"/grib/";
if [ $durationForecast -le 120 ]
then
    for ((i=0; i<$durationForecast; i++)) do
	numberFile=${arrayURL[$i]:85:4};
	"/bin/"\curl -o $downloadPathGrib$date"."$numberFile ${arrayURL[$i]};
	if ! [ -f $downloadPathGrib$date"."$numberFile ]; then
	    logMessage ${arrayURL[$i]}" file not loaded.";
	fi
    done
else
    for ((i=0; i<=120; i++)) do
	numberFile=${arrayURL[$i]:85:4};
	"/bin/"\curl -o $downloadPathGrib$date"."$numberFile ${arrayURL[$i]};
	if ! [ -f $downloadPathGrib$date"."$numberFile ]; then
	    logMessage ${arrayURL[$i]}" file not loaded.";
        fi
    done
    for ((i=123; i<=$durationForecast; i++)) do
	numberFile=${arrayURL[$i]:85:4};
        "/bin/"\curl -o $downloadPathGrib$date"."$numberFile ${arrayURL[$i]};
        if ! [ -f $downloadPathGrib$date"."$numberFile ]; then
	    logMessage ${arrayURL[$i]}" file not loaded.";
        fi
    done
fi
for layer in ${!layers[@]}; do
    key=${layer};
    value=${layers[${layer}]};
    if [ $value = "on" ]
    then
	echo $key >> $downloadPathGrib"/loaded_layers.txt";
    fi
done

#Конвертация GRIB файлов в GTiff
if ! [ -d $utilitePath ]
then
    logMessage "Utilite directory not found.";
fi
if ! [ -d $downloadPath$date"/"$hour"/gtif/" ]
then
    mkdir $downloadPath$date"/"$hour"/gtif/";
fi
pathGeoTiff=$downloadPath$date"/"$hour"/gtif/";
for fileGrib in "$downloadPathGrib"*;
do
    numberFile=${fileGrib:(-4)};
    if [ $numberFile != ".txt" ]
    then
	$utilitePath\gdal_translate -a_srs $typeWGS -ot Byte -of Gtiff $fileGrib $pathGeoTiff$date"-"$numberFile".tif";
	$utilitePath\gdalwarp -t_srs EPSG:$typeEPSG -wo SOURCE_EXTRA=1000 $pathGeoTiff$date"-"$numberFile".tif" $pathGeoTiff$date"-"$numberFile"-c.tif";
	createdGeoTiff=$pathGeoTiff$date"-"$numberFile"-c.tif";
	if ! [ -f $pathGeoTiff$date"-"$numberFile"-c.tif" ]; then
	    logMessage $pathGeoTiff$date"-"$numberFile"-c.tif file not created or not converted.";
	fi
	rm $pathGeoTiff$date"-"$numberFile".tif";
    fi
done

#Создание WMS файлов карт
if ! [ -d $wmsFilesPath ]
then
    mkdir $wmsFilesPath;
fi
if ! [ -f $pathGeoTiff$date"-f000-c.tif" ]
then
    logMessage "Files in directory with GTiff files "$pathGeoTiff" not found. WMS files not created."
else
    index=0;
    currentHour=-1;
    coeffDate=0;
    numberTifFile=0;
    for fileGTiff in "$pathGeoTiff"*;
    do
	let "currentHour=currentHour+1";
	if [ $index -ge 120 ]
	then
	    let "currentHour=currentHour+2";
	fi
	if [ $currentHour -ge 24 ]
	then
	    let "currentHour=0";
	fi
	if [ $index -lt 120 ]
	then
	    let "coeffDate=index/24";
	    numberTifFile=$(addZeroToNumber $index);
	else
	    let "coeffDate=5+(index-120)/8";
	    let "numberTifFile=120+(index-120)/8";
	fi
	currentDateInSecond=$(date -d $date +%s);
	let "countSecondCoeffDate=coeffDate*60*60*24";
	let "resultDateInSecond=currentDateInSecond+countSecondCoeffDate";
	dateWmsFile=$(date --date @$resultDateInSecond +"%Y%m%d");
	nameWmsFile=$dateWmsFile"_"$(addOneZeroToNumber $currentHour)"_wmsmap.map";
	echo -e "MAP\r\n\tNAME METEOPORTAL\r\n\tSTATUS ON\r\n\tSIZE 768 768\r\n\tEXTENT -180 -90 180 90\r\n\tUNITS DD\r\n\tIMAGECOLOR 255 255 255" >> $wmsFilesPath$nameWmsFile;
	echo -e "PROJECTION\r\n\t\"init=epsg:4326\"\r\nEND" >> $wmsFilesPath$nameWmsFile;
	echo -e "OUTPUTFORMAT\r\n\tNAME \"aggJPEG\"\r\n\tDRIVER \"AGG/JPEG\"\r\n\tMIMETYPE \"image/jpg\"\r\n\tIMAGEMODE RGB\r\n\tEXTENSION \"jpg\"\r\nEND" >> $wmsFilesPath$nameWmsFile;
	echo -e "WEB\r\n\tMINSCALE 1000\r\n\tMAXSCALE 10000000" >> $wmsFilesPath$nameWmsFile;
	echo -e "METADATA\r\n\t\"wms_title\" \"Meteoportal\"\r\n\t\"wms_onlineresource\" \""$onlineUrl"wmsmap"$dateWmsFile"_"$(addOneZeroToNumber $currentHour)"\"\r\n\t\"wms_server_version\" \"1.3.0\"\r\n\t\"wms_srs\" \"EPSG:3395 EPSG:4326 EPSG:3857\"\r\n\t\"wms_enable_request\" \"*\"\r\n\t\"wms_encoding\" \"UTF8\"\r\nEND\r\nEND" >> $wmsFilesPath$nameWmsFile;
	echo -e "### Layers start\r\nLAYER\r\n\tPROJECTION\r\n\t\t\"init=epsg:"$typeEPSG"\"\r\n\tEND" >> $wmsFilesPath$nameWmsFile;
	echo -e "\tNAME \"clouding\"" >> $wmsFilesPath$nameWmsFile;
	echo -e "\tMETADATA\r\n\t\t\"wms_title\" \"gfs clouding\"\r\n\t\t\"wms_srs\" \"EPSG:"$typeEPSG"\"\r\n\t\t\"wms_enable_request\" \"*\"\r\n\tEND" >> $wmsFilesPath$nameWmsFile;
	echo -e "\tDATA "$pathGeoTiff$date"-f"$numberTifFile"-c.tif" >> $wmsFilesPath$nameWmsFile;
	echo -e "\tTYPE RASTER\r\n\tCLASSITEM \"[pixel]\"\r\n\tSTATUS ON\r\n\tPROCESSING \"RESAMPLE=BILINEAR\"\r\n\tOFFSITE 0 0 0" >> $wmsFilesPath$nameWmsFile;
	echo -e "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 0 AND [pixel] <= 0)\r\n\t\tSTYLE\r\n\t\t\tCOLOR "$color"\r\n\t\t\tDATARANGE  0.0 0.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 0\r\n\t\tEND\r\n\tEND" >> $wmsFilesPath$nameWmsFile;
	echo -e "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 1 AND [pixel] <= 10)\r\n\t\tSTYLE\r\n\t\t\tCOLOR "$color"\r\n\t\t\tDATARANGE  1.0 10.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 10\r\n\t\tEND\r\n\tEND" >> $wmsFilesPath$nameWmsFile;
	echo -e "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 11 AND [pixel] <= 20)\r\n\t\tSTYLE\r\n\t\t\tCOLOR "$color"\r\n\t\t\tDATARANGE  11.0 20.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 20\r\n\t\tEND\r\n\tEND" >> $wmsFilesPath$nameWmsFile;
	echo -e "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 21 AND [pixel] <= 30)\r\n\t\tSTYLE\r\n\t\t\tCOLOR "$color"\r\n\t\t\tDATARANGE  21.0 30.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 30\r\n\t\tEND\r\n\tEND" >> $wmsFilesPath$nameWmsFile;
	echo -e "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 31 AND [pixel] <= 40)\r\n\t\tSTYLE\r\n\t\t\tCOLOR "$color"\r\n\t\t\tDATARANGE  31.0 40.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 40\r\n\t\tEND\r\n\tEND" >> $wmsFilesPath$nameWmsFile;
	echo -e "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 41 AND [pixel] <= 50)\r\n\t\tSTYLE\r\n\t\t\tCOLOR "$color"\r\n\t\t\tDATARANGE  41.0 50.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 50\r\n\t\tEND\r\n\tEND" >> $wmsFilesPath$nameWmsFile;
	echo -e "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 51 AND [pixel] <= 60)\r\n\t\tSTYLE\r\n\t\t\tCOLOR "$color"\r\n\t\t\tDATARANGE  51.0 60.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 60\r\n\t\tEND\r\n\tEND" >> $wmsFilesPath$nameWmsFile;
	echo -e "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 61 AND [pixel] <= 70)\r\n\t\tSTYLE\r\n\t\t\tCOLOR "$color"\r\n\t\t\tDATARANGE  61.0 70.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 70\r\n\t\tEND\r\n\tEND" >> $wmsFilesPath$nameWmsFile;
	echo -e "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 71 AND [pixel] <= 80)\r\n\t\tSTYLE\r\n\t\t\tCOLOR "$color"\r\n\t\t\tDATARANGE  71.0 80.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 80\r\n\t\tEND\r\n\tEND" >> $wmsFilesPath$nameWmsFile;
	echo -e "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 81 AND [pixel] <= 90)\r\n\t\tSTYLE\r\n\t\t\tCOLOR "$color"\r\n\t\t\tDATARANGE  81.0 90.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 90\r\n\t\tEND\r\n\tEND" >> $wmsFilesPath$nameWmsFile;
	echo -e "\tCLASS\r\n\t\tEXPRESSION ([pixel] >= 91 AND [pixel] <= 100)\r\n\t\tSTYLE\r\n\t\t\tCOLOR "$color"\r\n\t\t\tDATARANGE  91.0 100.0\r\n\t\t\tRANGEITEM \"[pixel]\"\r\n\t\t\tOPACITY 100\r\n\t\tEND\r\n\tEND" >> $wmsFilesPath$nameWmsFile;
	echo -e "END #layer\r\nEND" >> $wmsFilesPath$nameWmsFile;
	let "index=index+1";
    done
fi

#Очистка старых файлов
currentDateInSecond=$(date -d $date +%s);
let "countSecondSafe=durationStorageFile*60*60*24";
let "resultDateInSecond=currentDateInSecond-countSecondSafe";
safeDate=$(date --date @$resultDateInSecond +"%Y%m%d");
for directory in "$downloadPath"*;
do
    nameDir=${directory:(-8)};
    if [ $nameDir -lt $safeDate ]
    then
	rm -R $downloadPath$nameDir;
    fi
done