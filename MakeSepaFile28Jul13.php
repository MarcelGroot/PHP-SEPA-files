<?php
// +---------------------------------------------------------------------------------+
// | File: MakeSEPAFile.php                                                          |
// |                                                                                 |
// | Author: Marcel Groot <marcelgroot@kpnmail.nl>                                  |
// |                                                                                 |
// | Version July 2013:                                                              |
// |   With the changes made in the 31March2013 version, the new batch               |
// |   generated in June 2013 was now also accepted by the ABN                       |
// |                                                                                 |
// | Version 31 March 2013                                                           |
// | - As all postbank numbers were rejected, Rabobank informed that the             |
// |   mandate info shall be unique for the ABN-bank.                                |
// |   Used now Lidnummer in MandateInformation by:                                  |
// |   $Debitor->setMndtId("Lidnummer: $LidNummer");                                 |
// |                                                                                 |
// |                                                                                 |
// | Version 19 January 2013                                                         |
// | - According the Rabobank formaten book only true is allowed at batchbooking     |
// |   during the first phase of SEPA. Changed batchbooking from 'false' to 'true'   |
// |                                                                                 |
// | Version: 4 January 2013                                                         |
// | - added comments, updated reference                                             |
// | - updated reference for                                                         |
// |    XML message for SEPA Direct Debit Inititiation                               |
// |    Implementation Guidelines for the Netherlands                                |
// |    Vereniging van Nederlandse Banken                                            |
// |   from Version 2.2 - Februari 2011 to Version 6.0 - March 2012                  |
// |                                                                                 |
// | Version: 13 August 2012                                                         |
// |                                                                                 |
// | As per February 2014, the clieop03-file will be deprecated and a new            |
// | file according pain.008.001.02 format will be mandatory                         |
// | This scripts receives a transation wishdate and an array with all the invoices  |
// | from its calling script PrepareSEPA.php.  With that information the script      |
// | generates an XML file according pain.008.001.02 format, which can be uploaded   |
// | to the bank                                                                     |
// |                                                                                 |
// +---------------------------------------------------------------------------------+

    
    
// This file is called from PrepareSEPA.php. 
// In PrepareSEPA.php the user selects via an HTML page the invoices for the incasso batch
// which are passed on to this script
//
// Inputs to this script are:
//   - $WishDate        = $_POST['WishDate'];     //"jjjj-mm-dd" or a date, the wished transaction date 
//   - $ArrayChecked    = $_POST['ArrayChecked']; //the key of the array contains the invoice numbers for incasso
//                                                //the array is prepared in HTML from PrepareSEPA.php
//                                                //and contains only invoice numbers with IBAN not NULL
//
//   - The tables Leden and Facturen containing the data from the members and from the invoices
//   - The table IBANBIC containing a crossreference between the Bank Identifier code in the IBAN and the BIC code
//     This table is created from the document downloaded from www.ibanbicservice.nl/files/OverzichtBICNummersNederland.pdf
//   - 2Feb13: above location became obsolete, latest download now used from:
//     http://www.betaalvereniging.nl/europees-betalen/sepa-documentatie/bic-afleiden-uit-iban/
// 
// Includes: sepaclass.php, definitions of SEPA file according pain.008.001.02 format.
//
// Output: XML file according pain.008.001.02 format.

    
    
    
// Variables used in this php-script which are used to generate the XML,  refer to below publication
// which can be downloaded from www.sepanl.nl:
//
//  'XML message for SEPA Direct Debit Inititiation
//   Implementation Guidelines for the Netherlands
//   Vereniging van Nederlandse Banken
//   Version 6.9 - March 2012'
//   
// Example given:
// 1.1 <MsgId> [1..1], MessageIdentification
// Format: Text, MaxLength: 35, MinLenght: 1
// $_MsgId     = "incassobatch $timestamp"; 
//
// "1.1" is the index where this variable is specified in the above mentioned publication
// "<MsgId> is the XML tag according above mentioned publication
// "[1..1] shows the occurence of the element according above mentioned publication
// Note:  [0..1] shows that the element can be present 0 times or 1 time. The element is optional
//        [1..1] shows that the element can only be present 1 time. The element is mandatory 
//        [1..n] shows that the element is mandatory and can be present 1 to n times
// "MessageIdentification" shows the full wording for the abbreviated XML tag according above mentioned publication
// "Format: Text, MaxLength: 35, MinLenght: 1" shows the format according above mentioned publication
// "$_MsgId" is the variable name used in the script for XML tag <MsgId>
//


  
//temporary errors on    
//ini_set('display_errors', "on");

session_start();
//connect to our database
include_once('../../private/includes/ConnectToDatabase.inc');

//include login functions
require_once('../../private/includes/login_funcs.inc');

//include sepa classes
include_once('sepaclass.php');    

//skip everything if user is not logged in
if(user_isloggedin())
{ 
  //check if user is authorized
  $User   = $_SESSION['user'];
  $query  = "SELECT EnableFacturen
             FROM   Administratie
             WHERE  loginnaam = '$User'";
  $result = mysql_query($query);
  $row    = mysql_fetch_row($result);

  if ($row[0]==1)//user is geauthoriseerd
  { 
  
  //fetch the post
  $WishDate        = $_POST['WishDate'];     //"jjjj-mm-dd" or a date, the wished transaction date  
  $ArrayChecked    = $_POST['ArrayChecked']; //the key of the array contains the invoice numbers for incasso
                                             //the array is prepared in HTML from PrepareSEPA.php
    
  if (isset($_POST['ArrayChecked']))//only continue if exists.
  {
   
   //For debugging how the array arrives into this script use             
   //print_r($ArrayChecked);
   //Note: only invoice numbers which are 'checked' in the previous script arrive here
              
   //create timestamps for MessageID and CreationDateTime
   $todaysdate = date('Y-m-d' , time()); 
   $todaystime = date('H:i:s' , time());
   $timestamp  = $todaysdate."T".$todaystime; //Format 2012-05-12T15:03:34;
   
   //create a new instance of XMLPain
   $XMLPain = new XMLPain;

   $XMLPain->setNm('Your sporting club name');
     
   //create new instance of PaymentInformation
   $XMLPaymentInformation = new PaymentInformation;

   //built the Payment Information Header
   $XMLPaymentInformation->setCdtrNm('Your sporting club name, Ledenadministratie');
   $XMLPaymentInformation->setPmtInfId('Your sporting club name '.$XMLPain->getCreDtTm());
   //According the Rabobank format book only true is allowed at batchbooking
   //during the first phase of SEPA 
   $XMLPaymentInformation->setBtchBookg('true'); 
   $XMLPaymentInformation->setCdtrPstlAdr1('Your address row 1');
   $XMLPaymentInformation->setCdtrPstlAdr2('Your address row 2');
   $XMLPaymentInformation->setCdtrCtryOfRes('NL');
   $XMLPaymentInformation->setCdtrIBAN('Your IBAN');
   $XMLPaymentInformation->setCdtrBIC('Your BIC');
   $XMLPaymentInformation->setCstmrNb('Your Custumer number');
                   
                
  //+--------------------------------------------------------------------------+           
  //| Obtain data from the databases Leden and Facturen                        |
  //| This data is used to fill in the input variables for the XML             |
  //| The while loop fetches every round the data from an invoice              |
  //| and distills and compiles from this data the input variables for the XML |
  //+--------------------------------------------------------------------------+
  
  $NumberOfElements = count($ArrayChecked); // Number of invoices is the number of iterations
  $current_item     = reset($ArrayChecked); // Point to the first invoice in the row
  $_CtrlSum         = 0.0;                  // Initialize to zero, update in the while loop
                                            //    Use later to replace the string ControlSum
  while($NumberOfElements>0)
  {
    $Factuurnummer    = key($ArrayChecked);
    $query = "SELECT  Leden.Banknummer, Leden.Naam, Leden.Voornaam,
                      Leden.OuderVerzorger, Leden.Woonplaats,
                      Facturen.Factuurnummer, Facturen.Factuurbedrag,
                      Facturen.Omschrijving11, Facturen.Omschrijving12,
                      Facturen.Omschrijving13, Facturen.Omschrijving21,
                      Facturen.Omschrijving22, Facturen.Omschrijving23,
                      Facturen.Omschrijving33, Leden.IBAN,
                      Leden.Liddatum, Leden.Lidnummer                  
              FROM    Leden, Facturen
              WHERE   Leden.Lidnummer = Facturen.Lidnummer
              AND     Facturen.Factuurnummer = '$Factuurnummer'";
                 
    
    $result           = mysql_query($query);       
    $row              = mysql_fetch_row($result);
    
    //$LidBanknummer    = $row[0];     //not used
    $Naam             = $row[1];
    $Voornaam         = $row[2];
    $OuderVerzorger   = $row[3];
    //$Woonplaats       = $row[4];     //not used
    //$Factuurnummer    = $row[5];     //already known
    $Factuurbedrag    = $row[6];
    $Omschrijving11   = $row[7];
    $Omschrijving12   = $row[8];
    $Omschrijving13   = $row[9];
    $Omschrijving21   = $row[10];
    $Omschrijving22   = $row[11];
    $Omschrijving23   = $row[12];
    $Omschrijving33   = $row[13];
    $LidIBAN          = $row[14];
    $LidDatum         = $row[15];
    $LidNummer        = $row[16]; //31Maart2013, added Lidnummer, to be used in mandate info
        
    //Prepare one description
    $Omschrijving1 = "Factuurnr: ".$Factuurnummer;
    $Omschrijving2 = $Omschrijving11.' '.$Voornaam.' '.$Naam.' '.$Omschrijving12;
    $Omschrijving3 = $Omschrijving13;
    $Omschrijving4 = $Omschrijving21;
    $Omschrijving5 = $Omschrijving22;
    $Omschrijving6 = $Omschrijving23;
    $Omschrijving7 = $Omschrijving33;
    
    //Prepare a name
    //if an ouder/verzorger is present, use their name
    if($OuderVerzorger!="")
    {
      $Vollenaam = $OuderVerzorger;     
    }
    //else use the names of the member
    else
      $Vollenaam = $Voornaam." ".$Naam;
           
    //Substract the Bank Code from the IBAN and determine the corresponding BIC code from the table IBANBIC                    
    $BankCode = substr($LidIBAN,4,4);
    $query    = "SELECT BIC                   
                 FROM IBANBIC
                 WHERE IbanBANKCode = '$BankCode'";
    $result   = mysql_query($query);       
    $row      = mysql_fetch_row($result);
    $LidBIC   = $row[0]; 
                    
    //Compile and fill in the variables for the <DrctDbtTxInf> part  
        
    //Compile the description
    $_Description   = "$Omschrijving1, $Omschrijving2"; 
    if (strlen($Omschrijving3)>1) //avoid comma's or empty strings at the end
      $_Description =  $_Description . ", ".  $Omschrijving3;           
    if (strlen($Omschrijving4)>1) //avoid comma's or empty strings at the end
      $_Description =  $_Description . ", ".  $Omschrijving4;                
    if (strlen($Omschrijving5)>1) //avoid comma's or empty strings at the end
      $_Description =  $_Description . ", ".  $Omschrijving5;
    if (strlen($Omschrijving6)>1) //avoid comma's or empty strings at the end
      $_Description =  $_Description . ", ".  $Omschrijving6;
    if (strlen($Omschrijving7)>1) //avoid comma's or empty strings at the end
      $_Description =  $_Description . ", ".  $Omschrijving7;
      
    //limit to MaxLenght140 according guideline             
    if (strlen($_Description)>140)  
      $_Description = substr($_Description,0,139);
   
   
    //Create a new debitor
    $Debitor = new DirectDebitTransactionInformation;

    //built the debitor information
  
    $Debitor->setEndToEndId("Your club name Factuurnr $Factuurnummer");
    //OPTIONAL: $_InstdAmt   = number_format($Factuurbedrag, 2); //string number format to 2 decimals
    $Debitor->setInstdAmt($Factuurbedrag*1.00);
    $Debitor->setDbtrBIC("$LidBIC");
    $Debitor->setDbtrNm("$Vollenaam");
    $Debitor->setDbtrIBAN("$LidIBAN");
    $Debitor->setDescription("$_Description"); 
    $Debitor->setMndtId("Lidnummer: $LidNummer");  //31March2013, adjusted mandate info to be unique
    $Debitor->setDtOfSgntr("$LidDatum");  //Format 2012-05-12 according guideline page 79 only date (Page 38 Datetime)
   

    //add a DirectDebitTransactionInformation block to the PaymentInformation block    
    $XMLPaymentInformation->addDrctDbtTxInf(
                                             $Debitor->getXMLDirectDebitTransactionInformation(),
                                             $Debitor->getInstdAmt()
                                           );               
                    
                    
    //move the pointer to the next invoice, decrement the counter for the while loop                  
    $current_item = next($ArrayChecked);
    $NumberOfElements--;          

      
  }//end while
       
  //add the PaymentInformation block to the XML main file  
  $XMLPain->addPaymentInformationBlock(
                                       $XMLPaymentInformation->getXMLPaymentInfo(),
                                       $XMLPaymentInformation->getPmtInfNbOfTxs(),
                                       $XMLPaymentInformation->getPmtInfCtrlSum()
                                      );             
    
  //Create the SEPA file and download it to the user 
  //get the complete XML text
    
  $XMLText       = $XMLPain->getXMLText();  
    
    $fd   = fopen("downloads/SEPA.xml","wb") or die ("fout bij aanmaken XML bestand");//read and write, overwites last
    $fout = fwrite($fd,$XMLText);     
    fclose($fd); 
        
    // open the file in a binary mode
    $name = 'downloads/SEPA.xml';
    $fp   = fopen($name, 'rb');

    // send the right headers
    header("Content-Type: text/xml"); 
    header("Content-Disposition: attachment; filename=SEPA.xml");
    header("Content-Transfer-Encoding: binary");

    // dump the file and stop the script
    fpassthru($fp);
    fclose($fp);
    exit;
  }//end if (isset($_POST['ArrayChecked']))//only continue if exists.
  else 
 
$message = <<<MESSAGE
<html>
<body>
<h1> Er is iets misgegaan </h2>
</body>
</html>
MESSAGE;
    print("$message");

            
  }
  else //user is niet geauthoriseerd
  {
    print("User is NIET geauthoriseerd voor deze aktie<BR>\n
           Neem kontakt op met de ledenadministratie<BR>\n");
  }
}
else
  print("User is NIET ingelogd<BR>\n");

?>
