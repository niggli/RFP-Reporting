;<?php
;die();
;/*
; Konfiguration für CAMO Reporting aus vereinsflieger.de

; Hier Login und Passwort für das Einloggen in Vereinsflieger.de angeben
;
[vereinsflieger]
login_name = "name"
password = "password"

;
; Vereins-spezifische Einstellungen. Kennzeichen in Listen mit Kommata trennen. Zeitzone muss hier http://php.net/manual/en/timezones.php
; vorkommen
;
[club]
airport = "Airport"
timezone = "Europe/Zurich"
towplanes = "HB-ABC,HB-DEF,HB-GHI"
gliders = "HB-1111,HB-2222,HB-3333"
excludelist = "HB-4444"
flighttype_pax = "4"
flighttypes_training = "2,8,11,12,13,14,17,18"
filename_suffix = "-airport-landings.xls"

;
; Programm-Modus: "daily" schickt die Fluege vom heutigen Tag an die CAMO, "lastmonth" die Fluege des letzten Monats.
;
[modus]
mode = "daily"

;
; Konfiguration der gängisten variablen Einstellungen für den Mailversand.
; Wichtig bei Receivers folgendes Format verwenden: Mailadresse:Name des Empfaengers
; mehrere solche Kombinationen sind möglich, wenn sie durch Kommata getrennt sind.
; 
[mail]
smtp_server = "smtp.gmail.com"
smtp_login = "name@gmail.com"
smtp_passwd = "password"
from_address = "name@gmail.com"
from_name = "RFP-Reporting"
receivers = "name@domain.com: Name One"
admin = "admin@domain.com:Admin Guy"

;*/

