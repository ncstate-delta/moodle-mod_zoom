<?php

require_once($CFG->dirroot.'/mod/zoom/classes/Enums/BaseEnum.php');

class TimeZone extends BaseEnum
{
    const Midway_Island_Samoa = 'Pacific/Midway' ;

    const Pago_Pago = 'Pacific/Pago_Pago' ;

    const Hawaii = 'Pacific/Honolulu' ;

    const Alaska = 'America/Anchorage' ;

    const Vancouver = 'America/Vancouver' ;

    const Pacific_Time_US_and_Canada = 'America/Los_Angeles' ;

    const Tijuana = 'America/Tijuana' ;

    const Edmonton = 'America/Edmonton' ;

    const Mountain_Time_US_and_Canada = 'America/Denver' ;

    const Arizona = 'America/Phoenix' ;

    const Mazatlan = 'America/Mazatlan' ;

    const Winnipeg = 'America/Winnipeg' ;

    const Saskatchewan = 'America/Regina' ; 

    const Central_Time_US_and_Canada = 'America/Chicago' ;

    const America_Guatemala = 'America/Mexico_City' ;

    const Guatemala = 'America/Guatemala' ;

    const El_Salvador= 'America_El_Salvador' ;

    const Managua = 'America/Managua' ;

    const Costa_Rica = 'America/Costa_Rica' ;

    const Montreal = 'America/Montreal' ;

    const Eastern_Time_US_and_Canada = 'America/New_York' ;

    const Indiana_East = 'America/Indianapolis' ;

    const Panama = 'America/Panama' ;

    const Bogota = 'America/Bogota' ;

    const Lima = 'America/Lima' ;

    const Halifax = 'America/Halifax' ;

    const Puerto_Rico = 'America/Puerto_Rico' ; 

    const Caracas = 'America/Caracas' ;
    
    const Santiago = 'America/Santiago' ;
    
    const Newfoundland_and_Labrador = 'America/St_Johns' ;

    const Montevideo = 'America/Montevideo' ; 

    const Brasilia = 'America/Araguaina' ;

    const Buenos_Aires_Georgetown = 'America/Argentina/Buenos_Aires' ;

    const Greenland = 'America/Godthab' ;

    const Sao_Paulo = 'America/Sao_Paulo' ;

    const Azores = 'Atlantic/Azores' ;

    const Atlantic_Time_Canada = 'Canada/Atlantic' ;
 
    const Cape_Verde_Islands = 'Atlantic/Cape_Verde' ; 

    const Universal_Time_UTC = 'UTC' ;

    const Greenwich_Mean_Time = 'Etc/Greenwich' ;

    const Belgrade_Bratislava_Ljubljana = 'Europe/Belgrade' ;

    const Sarajevo_Skopje_Zagreb = 'CET' ; 

    const Reykjavik = 'Atlantic/Reykjavik' ;

    const Dublin = 'Europe/Dublin' ;

    const London = 'Europe/London' ; 

    const Lisbon = 'Europe/Lisbon' ; 

    const Casablanca = 'Africa/Casablanca' ;

    const Nouakchott = 'Africa/Nouakchott' ;

    const Oslo = 'Europe/Oslo' ;

    const Copenhagen = 'Europe/Copenhagen' ; 

    const Brussels = 'Europe/Brussels' ; 

    const Amsterdam_Berlin_Rome_Stockholm_Vienna = 'Europe/Berlin' ;

    const Helsinki = 'Europe/Helsinki' ;

    const Amsterdam = 'Europe/Amsterdam' ;

    const Rome = 'Europe/Rome' ;

    const Stockholm = 'Europe/Stockholm' ;
 
    const Vienna = 'Europe/Vienna' ;

    const Luxembourg = 'Europe/Luxembourg' ;

    const Paris = 'Europe/Paris' ;

    const Zurich = 'Europe/Zurich' ;

    const Madrid = 'Europe/Madrid' ;

    const West_Central_Africa = 'Africa/Bangui' ;

    const Algiers = 'Africa/Algiers' ;

    const Tunis = 'Africa/Tunis' ;

    const Harare_Pretoria = 'Africa/Harare' ;

    const Nairobi = 'Africa/Nairobi' ; 

    const Warsaw = 'Europe/Warsaw' ; 

    const Prague_Bratislava = 'Europe/Prague' ;
 
    const Budapest = 'Europe/Budapest' ; 

    const Sofia = 'Europe/Sofia' ;

    const Istanbul = 'Europe/Istanbul' ;

    const Athens = 'Europe/Athens' ;

    const Bucharest = 'Europe/Bucharest' ;

    const Nicosia = 'Asia/Nicosia' ;

    const Beirut = 'Asia/Beirut' ;

    const Damascus = 'Asia/Damascus' ;

    const Tripoli = 'Africa/Tripoli' ; 

    const Johannesburg = 'Africa/Johannesburg' ;

    const Moscow = 'Europe/Moscow' ; 

    const Baghdad = 'Asia/Baghdad' ;

    const Kuwait = 'Asia/Kuwait' ;
 
    const Jerusalem = 'Asia/Jerusalem' ; 

    const Amman = 'Asia/Amman' ;

    const Cairo = 'Africa/Cairo' ;

    const Riyadh = 'Asia/Riyadh' ;

    const Bahrain = 'Asia/Bahrain' ;

    const Qatar = 'Asia/Qatar' ;

    const Aden = 'Asia/Aden' ; 

    const Tehran = 'Asia/Tehran' ; 

    const Khartoum = 'Africa/Khartoum' ;

    const Djibouti = 'Africa/Djibouti' ;
 
    const Mogadishu = 'Africa/Mogadishu' ; 

    const Dubai = 'Asia/Dubai' ;

    const Muscat = 'Asia/Muscat' ;

    const Baku_Tbilisi_Yerevan = 'Asia/Baku' ;

    const Kabul = 'Asia/Kabul' ;
 
    const Yekaterinburg = 'Asia/Yekaterinburg' ; 

    const Islamabad_Karachi_Tashkent = 'Asia/Tashkent' ;

    const Kathmandu = 'Asia/Kathmandu' ;

    const Novosibirsk = 'Asia/Novosibirsk' ;

    const Almaty = 'Asia/Almaty' ;

    const Dacca = 'Asia/Dacca' ;

    const Krasnoyarsk = 'Asia/Krasnoyarsk' ; 

    const Astana_Dhaka ='Asia/Dhaka' ;

    const Bangkok = 'Asia/Bangkok' ;

    const Vietnam = 'Asia/Saigon' ;

    const Jakarta = 'Asia/Jakarta' ; 

    const Irkutsk_Ulaanbaatar = 'Asia/Irkutsk' ;

    const Beijing_Shanghai = 'Asia/Shanghai' ;

    const Hong_Kong = 'Asia/Hong_Kong' ;

    const Taipei = 'Asia/Taipei' ; 
 
    const Kuala_Lumpur = 'Asia/Kuala_Lumpur' ;

    const Singapore = 'Asia/Singapore' ;

    const Perth = 'Australia/Perth' ; 

    const Yakutsk = 'Asia/Yakutsk' ; 

    const Seoul = 'Asia/Seoul' ; 

    const Osaka_Sapporo_Tokyo = 'Asia/Tokyo' ; 

    const Darwin = 'Australia/Darwin' ; 

    const Adelaide = 'Australia/Adelaide' ;

    const Vladivostok = 'Asia/Vladivostok' ; 

    const Guam_Port_Moresby = 'Pacific/Port_Moresby' ; 
 
    const Brisbane = 'Australia/Brisbane' ; 

    const Canberra_Melbourne_Sydney = 'Australia/Sydney' ; 

    const Hobart = 'Australia/Hobart' ; 

    const Magadan = 'Asia/Magadan' ; 

    const Solomon_Islands = 'SST' ;

    const New_Caledonia = 'Pacific/Noumea' ; 

    const Kamchatka = 'Asia/Kamchatka' ;

    const Fiji_Islands_Marshall_Islands = 'Pacific/Fiji' ;

    const Auckland_Wellington = 'Pacific/Auckland' ;

    const Mumbai_Kolkata_New_Delhi = 'Asia/Kolkata' ; 

    const Kiev = 'Europe/Kiev' ; 

    const Tegucigalpa = 'America/Tegucigalpa' ;

    const Independent_State_of_Samoa = 'Pacific/Apia' ;

}
