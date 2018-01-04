<?php

  // RFP-Reporting
  //
  // This script reads flights from Vereinsflieger.de, checks them for relevance, generates an
  // Excel spreadsheet with the flight data and sends the file via mail. Rules this script obeyes:
  // - Don't report selflaunches, only tows
  // - Don't report flights from runway "Hartbelag"
  // - Don't report flights taking place at other airports
  // - Only report flights which land at home airport. Starts to another airport are not relevant
  // - Flights with a certain flighttype are marked as pax flights
  // - Flights with certain flighttypes are marked as training flights
  // - Flights with club gliders are marked as group flights, others as private flights
  // - Don't take remarks from vereinsflieger.de
  // - If a glider doesn't land at the home airfield, the flight is either a outlanding or a home-tow
  //    => If the glider is a club glider, the flight is considered a outlanding.
  //    => If the glider isn't a club glider and the word "Aussenlandung" isn't in the remarks, it's considered a home-tow
  // - ...
  //
  // Versions
  // 1.0 - 4.01.2018 First draft

  ini_set('display_errors', 1);
  error_reporting(E_ALL ^ E_NOTICE);

  $CONFIG_FILE = "RFP.cfg.php";

  $aBBentry = array (
              "date" => "",
              "glider_callsign" => "",
              "glider_starttime" => "",
              "glider_arrivaltime" => "",
              "towplane_callsign" => "",
              "towplane_starttime" => "",
              "towplane_arrivaltime" => "",
              "training" => "",
              "group" => "",
              "private" => "",
              "pax" => "",
              "remarks" => ""
  );
  $Flights = array();

  require_once('VereinsfliegerRestInterface.php');
  
  // Require autoload from composer. Use composer to install PHPSpreadsheet and PHPMailer
  require 'vendor/autoload.php';
  use PhpOffice\PhpSpreadsheet\Spreadsheet;
  use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
  use PHPMailer\PHPMailer\PHPMailer;
  
  $configuration = parse_ini_file ($CONFIG_FILE, 1);
  $club_gliders = explode (",",$configuration['club']['gliders']);
  $club_airport = $configuration['club']['airport'];
  $club_filename_suffix = $configuration['club']['filename_suffix'];
  $club_towplanes = explode (",",$configuration['club']['towplanes']);
  $flighttype_pax = $configuration['club']['flighttype_pax'];
  $flighttypes_training = explode (",",$configuration['club']['flighttypes_training']);
  $mode = $configuration['modus']['mode'];
  	
  date_default_timezone_set ( "UTC");
  $a = new VereinsfliegerRestInterface();
      
  $result = $a->SignIn($configuration['vereinsflieger']['login_name'],$configuration['vereinsflieger']['password'],0);

  if ($result) {
      if ($mode=="lastmonth")
      {
        // get number of days in month
        $firstdayint = strtotime("first day of previous month");
        $dateArray = getdate($firstdayint);
        $max = cal_days_in_month(CAL_GREGORIAN, $dateArray['mon'], $dateArray['mday']);
        echo "Mode: flights from last month. Max days in last month: $max<br />";
      } else // mode: daily
      {
        echo "Mode: flights from today<br />";
        $max=1;  
      }
       
      // Loop through all days of last month
      for ($daycounter=0; $daycounter<=($max-1);$daycounter++)
      {
        if ($mode=="lastmonth")
        {
          // get first day of month
          $firstdayint = strtotime("first day of previous month");
          $firstday = date_create("@$firstdayint");
          $daydate = date_add($firstday, date_interval_create_from_date_string("$daycounter days"));
          $datum = date_format($daydate, "Y-m-d");
          echo "Date: $datum ";
          sleep(5);
          $return = $a->GetFlights_date ($datum);
        } else // mode: daily
        {
          $daydate = new DateTime();
          // $daydate = new DateTime("2017-07-08"); //use for testing
          $datum = date_format($daydate, "Y-m-d");
          $return = $a->GetFlights_date ($datum);
        }
      
        
        if ($return)
        {
                
          $aResponse = $a->GetResponse();
          $no_Flights = count ($aResponse) - 1; // last element is httpresponse...
           
          if ($no_Flights > 0)
          {
            $counter = 0;
            for ($i=0; $i<$no_Flights;$i++)
            {
              //Test: output all flights to console
              echo "Flight: id: " . $aResponse[$i]["flid"] . " callsign: " . $aResponse[$i]["callsign"] . " starttime: " . $aResponse[$i]["departuretime"];
              echo "starttype: " . $aResponse[$i]["starttype"] . " arrivallocation: " . $aResponse[$i]["arrivallocation"] . " flidtow: " . $aResponse[$i]["flidtow"] . "<br />";
              
              //check if flight is relevant
              if ( ($aResponse[$i]["starttype"] == "1") //Starttype 1 = Eigenstart, 3 = F-Schlepp
                && ($aResponse[$i]["arrivallocation"] == $club_airport)
                && ($aResponse[$i]["flidtow"] > 0) 
                && !(preg_match("/[Hh]art[-]{0,1}[Bb]elag/", $aResponse[$i]["comment"])) )
              {
                echo "Relevant towflight found<br />";
                // Fill data of flight in Flights array
                $Flights[$counter] = $aBBentry;
                $Flights[$counter]["date"] = date_format($daydate, "d.m.Y");;
                $Flights[$counter]["towplane_callsign"] = $aResponse[$i]["callsign"];
                $Flights[$counter]["towplane_starttime"] = substr($aResponse[$i]["departuretime"], 0, 5);
                $Flights[$counter]["towplane_arrivaltime"] = substr($aResponse[$i]["arrivaltime"], 0, 5);
                if (in_array($aResponse[$i]["callsign"], $club_towplanes) )
                {
                  $Flights[$counter]["towplane_callsign"] = $aResponse[$i]["callsign"];
                } else
                {
                  // towplane was not in list, so leave callsign field empty
                  $Flights[$counter]["towplane_callsign"] = "";
                }
                
                // Find corresponding glider flight and get relevant data
                for ($j=0; $j<$no_Flights; $j++)
                {
                  if ($aResponse[$j]["flid"] == $aResponse[$i]["flidtow"])
                  {
                    $Flights[$counter]["glider_callsign"] = $aResponse[$j]["callsign"];
                    $Flights[$counter]["glider_starttime"] = substr($aResponse[$j]["departuretime"], 0, 5);
                    if ($aResponse[$j]["arrivallocation"] == $club_airport)
                    {
                      $Flights[$counter]["glider_arrivaltime"] = substr($aResponse[$j]["arrivaltime"], 0, 5);
                    } else
                    {

                      // Try to find out (as good as possible) if it's a outlanding or a tow
                      // back home for a external glider.
                      if (in_array($aResponse[$j]["callsign"], $club_gliders) )
                      {
                        $Flights[$counter]["remarks"] = "Aussenlandung, keine Landetaxe verrechnen.";
                        $Flights[$counter]["glider_arrivaltime"] = ""; // Leave arrivaltime empty so no landingfee will be invoiced
                      } else
                      {
                        if ((preg_match("/[Aa]ussen[-]{0,1}[Ll]andung/", $aResponse[$i]["comment"])) )
                        {
                          $Flights[$counter]["remarks"] = "Aussenlandung, keine Landetaxe verrechnen.";
                          $Flights[$counter]["glider_arrivaltime"] = ""; // Leave arrivaltime empty so no landingfee will be invoiced
                        } else
                        {
                          $Flights[$counter]["remarks"] = "Rückschlepp, Landung verrechnen";
                          $Flights[$counter]["glider_arrivaltime"] =  $Flights[$counter]["towplane_arrivaltime"]; // Pseudo landing time because landing was before start.
                        }
                      }
                    }
                    if (in_array($aResponse[$j]["ftid"], $flighttypes_training) )
                    {
                      $Flights[$counter]["training"] = "x";
                    } else
                    {
                      $Flights[$counter]["training"] = "";
                    }
                    if (in_array($aResponse[$j]["callsign"], $club_gliders) )
                    {
                      $Flights[$counter]["group"] = "x";
                      $Flights[$counter]["private"] = "";
                    } else
                    {
                      $Flights[$counter]["group"] = "";
                      $Flights[$counter]["private"] = "x";
                    }
                    if ($aResponse[$j]["ftid"] == $flighttype_pax)
                    {
                      $Flights[$counter]["pax"] = 1;
                      // Pax flights with club gliders count as training
                      if (in_array($aResponse[$j]["callsign"], $club_gliders) )
                      {
                        $Flights[$counter]["training"] = "x";
                      }
                    } else
                    {
                      $Flights[$counter]["pax"] = 0;
                    }
                    
                  }
                } //for
                $counter++;
              } else
              {
                echo "Flight not relevant<br />";
              }// if flight is relevant
             
            } // for loop over all flights
            
            // Sort with user specific function declared below
            usort($Flights, "compare_flights");
            
            echo "some flights found<br />";
            	
          } else
          {
            echo ("no flights<br />");
          }
        }
        else
        {
          echo ("Flug lesen NAK<br />");
        }
      } //for days in month
     
      // for debugging output table as in excel
      echo "Table of flights to be reported<br />";
      foreach ($Flights as $entry)
      {
        echo $entry["date"] . " " . $entry["glider_callsign"] . " " . $entry["glider_starttime"] . " " . $entry["glider_arrivaltime"];
        echo " " . $entry["towplane_callsign"] . " " . $entry["towplane_starttime"] . " " . $entry["towplane_arrivaltime"];
        echo " " . $entry["training"] . " " . $entry["group"] . " " . $entry["private"] . " " . $entry["pax"] . " " . $entry["remarks"] . "<br />";
      }
      echo "Table finished <br />";
      
      if(count($Flights) > 0)
      {
        // Generation of XLS file
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getStyle("A1:L1")->getFont()->setBold(true);
        $sheet->getStyle("A1:L1")->getFill()->getStartColor()->setARGB("FFD9D9D9"); //light grey
        $sheet->getStyle("A1:L1")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        foreach(range("A","K") as $col)
        {
          $sheet->getColumnDimension($col)->setWidth(12);
        }
        $sheet->getColumnDimension("L")->setWidth(25);
        
        //Fill in data, first header row
        $headers = array("Datum", "SF", "SFStart", "SFLandung", "SP", "SPStart", "SPLandung", "Schulung", "Gruppe", "Privat", "Pax", "Bemerkungen");
        $sheet->fromArray($headers, NULL, "A1");
        
        // Then flight data
        for ($k=0; $k<count($Flights); $k++)
        {
          $sheet->setCellValue("A" . ($k+2), $Flights[$k]["date"]);
          $sheet->setCellValue("B" . ($k+2), $Flights[$k]["glider_callsign"]);
          $sheet->setCellValue("C" . ($k+2), $Flights[$k]["glider_starttime"]);
          $sheet->setCellValue("D" . ($k+2), $Flights[$k]["glider_arrivaltime"]);
          $sheet->setCellValue("E" . ($k+2), $Flights[$k]["towplane_callsign"]);
          $sheet->setCellValue("F" . ($k+2), $Flights[$k]["towplane_starttime"]);
          $sheet->setCellValue("G" . ($k+2), $Flights[$k]["towplane_arrivaltime"]);
          $sheet->setCellValue("H" . ($k+2), $Flights[$k]["training"]);
          $sheet->setCellValue("I" . ($k+2), $Flights[$k]["group"]);
          $sheet->setCellValue("J" . ($k+2), $Flights[$k]["private"]);
          $sheet->setCellValue("K" . ($k+2), $Flights[$k]["pax"]);
          $sheet->setCellValue("L" . ($k+2), $Flights[$k]["remarks"]);
        }

        
        //Save spreadsheet to file
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
        $filename = date_format($daydate, "Ymd") . $club_filename_suffix;
        $writer->save($filename);
        
          
        //Create a new PHPMailer instance
        $mail = new PHPMailer;
        //Tell PHPMailer to use SendMail
        $mail->IsSendmail();
        //Enable SMTP debugging
        // 0 = off (for production use)
        // 1 = client messages
        // 2 = client and server messages
        $mail->SMTPDebug = 0;
        //Ask for HTML-friendly debug output
        $mail->Debugoutput = 'html';
        //Set the hostname of the mail server
        $mail->Host = gethostbyname($configuration['mail']['smtp_server']);
        // if your network does not support SMTP over IPv6
        //Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
        $mail->Port = 587;
        //Set the encryption system to use - ssl (deprecated) or tls
        $mail->SMTPSecure = 'tls';
        //Whether to use SMTP authentication
        $mail->SMTPAuth = true;
        //Username to use for SMTP authentication - use full email address for gmail
        $mail->Username = $configuration['mail']['smtp_login'];
        //Password to use for SMTP authentication
        $mail->Password = $configuration['mail']['smtp_passwd'];
        //Set who the message is to be sent from
        $mail->setFrom($configuration['mail']['from_address'], $configuration['mail']['from_name']);
        //Set an alternative reply-to address
        $mail->addReplyTo($configuration['mail']['from_address'], $configuration['mail']['from_name']);
        //Set who the message is to be sent to
  
        $receivers = explode (",", $configuration['mail']['receivers']);
        foreach ($receivers as $receiver)
        {
          $receiver_details = explode (":", $receiver);
          $mail->addAddress($receiver_details[0], $receiver_details[1]);
        }
        //Set the subject line
        $mail->Subject = "SGS Flugmeldung vom " . $datum;
        $mail->Body = "Anbei die " . $subject . "\n\n";
        //Replace the plain text body with one created manually
        $mail->AltBody = 'This is a plain-text message body';
        //Attach an image file
        $mail->addAttachment($filename);
        //send the message, check for errors
  
        if (!$mail->send())
        {
          echo "Mailer Error: " . $mail->ErrorInfo;
        } else
        {
          echo "Mail sent<br />";
        }    
        
      } else
      {
        echo ("No flights <br />");
      }
             
    }
    else
    {
      echo ("Login failed<br />");
    }

    
    
  function compare_flights($a, $b)
  {
    return strcmp($a["towplane_starttime"], $b["towplane_starttime"]);
  }

  
?>
