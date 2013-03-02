<?php
 
// +----------------------------------------------------------------------------------------+
// | File: sepaclass.php                                                                    |
// | Author: Marcel Groot, marcelgroot@svsparta.nl                                          |
// |                                                                                        |
// | Revision 2 Feb 2013                                                                    |
// | - Placed <Ctry> tag direct after <PstlAdr> after feedback from Rabobank                |
// |   at Creditor                                                                          |
// |                                                                                        |
// | Revision 18 Jan 2013                                                                   |
// | -Implemented feedback from Raboank and obtained their formatbook version 1.0           |
// |  dated 14Feb2011 from https://www.rabobank.com/en/float/fl/downloads.html              |
// |  - Deleted <Nm> tag in <CdtrScheId>                                                    |
// |  - Changed <CtryOfRes> back into <Ctry> at <Creditor>                                  |
// |  - Changed <Document....> tag according Rabobank format,                               |
// |    The example in the XML message, guideline showsat least a typing error              |
// | Note1: there are (again ) inconsistencies between this Rabobank format book and        |
// | the XML message for SEPA Direct Debit Initiation Implementation Guidelines for the     |
// | Netherlands.  Mr Frans Rietbergen would discuss internally at the Rabobank             |
// | Note2: according the Rabobank formaten book only true is allowed at batchbooking       |
// | during the first phase of SEPA                                                         |
// |                                                                                        |
// | Revision: 3 Jan 2013                                                                   |
// | - Updated according comments Rabobank                                                  |
// | - updated reference for                                                                |
// |    XML message for SEPA Direct Debit Inititiation                                      |
// |    Implementation Guidelines for the Netherlands                                       |
// |    Vereniging van Nederlandse Banken                                                   |
// |   from Version 2.2 - Februari 2011 to Version 6.0 - March 2012                         |
// | - Took out <GRP> tag                                                                   |
// | - Changed <Ctry> into <CtryOfRes> at <Creditor>                                        |
// +----------------------------------------------------------------------------------------+
    
    
// +----------------------------------------------------------------------------------------+
// | As per February 2014, payments are only possible with the IBAN                         |
// | (International Bank Account Number).                                                   |
// | Everybody has to use the new standards for payments and incasso's as per               |
// | 1 February 2014, which date has been determined by European law.                       |
// | As per this date all 32 countries within the SEPA (Single European Payment Area)       |
// | have to be switched over to the IBAN.                                                  |
// | De national clientbank- and bankclient standards will be replaced by the               |
// | SEPA XML formaat.  This SEPA format is about a format used by companies to standardize |
// | payments. For example the ClieOp file format or the BTL91 format will no longer be     |
// | in use. A new XML file format according Pain.008.001.02 format will  become mandatory. |
// |                                                                                        |
// | Each such XML message consists out of three main elements:                             |
// | - A Group header block, which contains general information.                            |
// | - A Payment Information block, which contains general information as well as one or    |
// |   more Transaction Information blocks.                                                 |
// | - A Transaction Information block, which is contained in a Payment Information block   |
// |   and contains details about the financial transaction.                                |
// |                                                                                        |
// | Dependent on local bank rules, different grouping methods are allowed                  |
// | - SINGLE: Indicates that for each Payment Information Block there shall be only one (1)|
// |   Transaction Information Block.                                                       |
// | - GROUPED: Indicates that there shall be only one (1) Payment Information Block, in    |
// |   which multiple Transaction Information blocks may be present.                        |
// | - MIXED: Indicates that there can be one or more Payment Information block(s) and      |
// |   each such block can contain one or more Transaction Information block(s)             |
// |                                                                                        |
// |   SINGLE                       GROUPED                      MIXED                      |
// |   Group Header                 Group Header                 Group Header               |
// |   Payment Information 1        Payment Information 1        Payment Information 1      |
// |    Transaction Information 1    Transaction Information 1    Transaction Information 1 |
// |   Payment Information 2         Transaction Information 2    Transaction Information 2 |
// |    Transaction Information 2    Transaction Information 3   Payment Information 2      |
// |   Payment Information 3                                      Transaction Information 3 |
// |    Transaction Infromation 3                                 Transaction Information 4 |
// |                                                                                        |
// |                                                                                        |                                                    
// | In this script three classes are defined to generate an XML file according             |
// | the pain.008.001.02 format:                                                            |
// | - The main class XMLPain, which defines the boundaries of the XML file and contains    |
// |   the Group header block.                                                              |
// | - The class PaymentInformation, which builts up a PaymentInformation block.            |
// |   Each Payment Information block shall be added into an entity of class XMLPain.       |
// | - The class DirectDebitTransactionInformation, which is a storage area for the         |
// |   information about a single debitor. This Transaction Information block shall be      |
// |   added for each debitor into an entity of a class PaymentInformation.                 |
// +----------------------------------------------------------------------------------------+
  
// +-------------------------------------------------------------------------------------------------------------------+  
// | Variables used inside the XML text,  refer to below publication which can be downloaded from www.sepanl.nl:       |
// |                                                                                                                   |
// | 'XML message for SEPA Direct Debit Inititiation                                                                   |
// |  Implementation Guidelines for the Netherlands                                                                    |
// |  Vereniging van Nederlandse Banken                                                                                |
// |  Version 6.0 - March 2012'                                                                                     |
// |                                                                                                                   |
// |  Example given:                                                                                                   |
// |  1.1 <MsgId> [1..1], MessageIdentification                                                                        |
// |  Format: Text, MaxLength: 35, MinLenght: 1                                                                        |
// |  $_MsgId     = "incassobatch $timestamp";                                                                         |
// |                                                                                                                   |
// | "1.1" is the index where this variable is specified in the above mentioned publication                            |
// | "<MsgId> is the XML tag according above mentioned publication                                                     |
// | "[1..1] shows the occurence of the element according above mentioned publication                                  |
// | Note:  [0..1] shows that the element can be present 0 times or 1 time. The element is optional                    |
// |        [1..1] shows that the element can only be present 1 time. The element is mandatory                         |
// |        [1..n] shows that the element is mandatory and can be present 1 to n times                                 |
// | "MessageIdentification" shows the full wording for the abbreviated XML tag according above mentioned publication  |
// | "Format: Text, MaxLength: 35, MinLenght: 1" shows the format according above mentioned publication                |
// | "$_MsgId" is the variable name used in this script for XML tag <MsgId>                                             |
// |                                                                                                                   |
// +-------------------------------------------------------------------------------------------------------------------+ 
    
ini_set('display_errors', "on");
    
class XMLPain

{

    //1.0 <GrpHdr>, [1..1], Group Header   
    //Group Header variables are:
    // - 1.1 <Msgid>, Messageidentification
    // - 1.2 <CreDtTm>, CreationDateTime
    // - 1.3 <Authstn>, Authorisation
    // - 1.6 <NbOfTxs>, NumberOfTransactions
    // - 1.7 <CtrlSum>, ControlSum
    // - 1.8 <InitPty>, InitiatingParty
    // - 1.9 <FwdgAgt>, ForwardingAgent
               
    //1.1 <MsgId> [1..1], MessageIdentification
    //Format: Text, MaxLength: 35, MinLenght: 1
    var $_MsgId ;  
    
    //1.2 <CreDtTm> [1..1], CreationDateTime
    //Format: ISODateTime e.g.: 2012-05-12T15:03:34; 
    var $_CreDtTm;     

    //1.3 <Authstn>, Authorisation 
    //Not used    
        
    //1.6 <NbOfTxs> [1..1], NumberOfTransactions
    //Format Max15NumericText
    var $_GrpHdrNbOfTxs = 0;
    
    //1.7 <CtrlSum> [0..1], ControlSum
    //Total of all individual amounts included in the message
    //Format: DecimalNumber, fractionDigits: 17 totalDigits: 18
    var $_GrpHdrCtrlSum = 0; 
    
                    
    
    //1.8 <InitgPty> [1..1], InititatingParty
    //Inititating Party variables are:
    // - <Nm>, Name
    // - <PstlAdr>, PostalAddress
    // - <Id>, Identification
    // - <CtryOfRes>, CountryOfResidence
    // - <CtctDtls>, ContactDetails
    
      //<Nm> [0..1], Name
      //Format: Text, MaxLength 70
      var $_Nm;
      
      //<PstlAdr>[0..1], PostalAddress
      //Not used
      
      //<Id> [0..1], Identification
      //Format: OrganisationIdentification: Either ‘BIC or BEI’ or one occurrence of ‘Other’ is allowed
      //Format: PrivateIdentification: Either ‘Date and Place of Birth’ or one occurrence of ‘Other’ is allowed.
      //Not used

      //<CtryOfRes> [0..1], CountryOfResidence
      //Format: Code
      //Not used
      
      //<CtctDtls> [0..1], ContactDetails
      //Not used 
    
    //1.9 <FwdgAgt>, ForwardingAgent    
    //Not used
    
 
    //placeholder for PaymentInformation Block
    var $XMLPaymentInformationText ='';
    
    
    
    
// built the properties <MsgId> and <CreDtTm >
 function __construct() {
   $TodaysDate           = date('Y-m-d' , time()); 
   $TodaysTime           = date('H:i:s' , time());
   $TimeStamp            = $TodaysDate."T".$TodaysTime; //Format 2012-05-12T15:03:34 according guideline
   $DateOverFiveDaysInt  = mktime(0, 0, 0, date("m")  , date("d")+5, date("Y"));
   $DateOverFiveDays     = date('Y-m-d' , $DateOverFiveDaysInt);
   
   //set default values if not changed by a set-function later
   $this->_MsgId         = substr("DirDebBatch $TimeStamp",0,34);
   
   $this->_CreDtTm       = "$TimeStamp";
   
   
   
 }

 // property <MsgId>
 function setMsgId($Value) {
       $this->_MsgId = substr($Value,0,34);       
 }
 
 // property <CreDtTm>
 function getCreDtTm() {
       return $this->_CreDtTm;       
 }
 
 // property <Nm>
 function setNm($Value) {
       $this->_Nm = substr($Value,0,69);       
 }
 
  
//add a PaymentInformation block to the XML
//update the GroupHeaderControlSum and the GroupHeaderNumberOfTransactions
 function addPaymentInformationBlock($XMLPaymentInformationText,$PmtInfNbOfTxs,$PmtInfCtrlSum){
    
     $this->XMLPaymentInformationText .=  $XMLPaymentInformationText;
     $this->XMLPaymentInformationText .= "\r\n";
     $this->_GrpHdrCtrlSum += $PmtInfCtrlSum; 
     $this->_GrpHdrNbOfTxs += $PmtInfNbOfTxs;
     
 }
 
 //return the XML text for the <PmtInf> block
 function getXMLPaymentInformation(){
     
     return $this->XMLPaymentInformationText;
 }
 
 //create and return the XML Header
 function getXMLHeader() {
     
$XMLheader = <<<XMLHEADER
<?xml version="1.0" encoding="UTF-8"?>
<Document xsi:schemaLocation="urn:iso:std:iso:20022:tech:xsd:pain.008.001.02 pain.008.001.02.xsd" xmlns="urn:iso:std:iso:20022:tech:xsd:pain.008.001.02" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <CstmrDrctDbtInitn>
XMLHEADER;
      
    return $XMLheader;
 }  


 
//create and return the XML GroupHeader
//deleted Grpg XML-tag
 function getXMLGroupHeader(){
      
    $XMLgroupheader = <<<XMLGROUPHEADER
    <GrpHdr>
      <MsgId>$this->_MsgId</MsgId> 
      <CreDtTm>$this->_CreDtTm</CreDtTm> 
      <NbOfTxs>$this->_GrpHdrNbOfTxs</NbOfTxs> 
      <CtrlSum>$this->_GrpHdrCtrlSum</CtrlSum>
      <InitgPty>
        <Nm>$this->_Nm</Nm> 
      </InitgPty>
    </GrpHdr>
XMLGROUPHEADER;
    
    return $XMLgroupheader;
 } 
 
 
  
 //create and return the XML Footer
 function getXMLFooter(){
   
$XMLfooter = <<<XMLFOOTER
  </CstmrDrctDbtInitn>
</Document>
XMLFOOTER;

    return $XMLfooter;
 } 

 

 //compile, validate and return the XML file text
 function getXMLText(){
    
      $XMLText       = $this->getXMLHeader();
      $XMLText      .= "\r\n";
      $XMLText      .= $this->getXMLGroupHeader();
      $XMLText      .= "\r\n";
      $XMLText      .= $this->getXMLPaymentInformation();
      $XMLText      .= "\r\n";
      $XMLText      .= $this->getXMLFooter();
      
      // Only below characters can be used within the XML tags according the guideline.                
      // a b c d e f g h i j k l m n o p q r s t u v w x y z
      // A B C D E F G H I J K L M N O P Q R S T U V W X Y Z
      // 0 1 2 3 4 5 6 7 8 9
      // / - ? : ( ) . , ‘ +
      // Space
      //
      // Create a normalized array and cleanup the string $XMLText for unexpected characters in names
      $normalizeChars = array(
            'Á'=>'A', 'À'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Å'=>'A', 'Ä'=>'A', 'Æ'=>'AE', 'Ç'=>'C',
            'É'=>'E', 'È'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Í'=>'I', 'Ì'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ð'=>'Eth',
            'Ñ'=>'N', 'Ó'=>'O', 'Ò'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O',
            'Ú'=>'U', 'Ù'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y',
   
            'á'=>'a', 'à'=>'a', 'â'=>'a', 'ã'=>'a', 'å'=>'a', 'ä'=>'a', 'æ'=>'ae', 'ç'=>'c',
            'é'=>'e', 'è'=>'e', 'ê'=>'e', 'ë'=>'e', 'í'=>'i', 'ì'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'eth',
            'ñ'=>'n', 'ó'=>'o', 'ò'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o',
            'ú'=>'u', 'ù'=>'u', 'û'=>'u', 'ü'=>'u', 'ý'=>'y',
           
            'ß'=>'sz', 'þ'=>'thorn', 'ÿ'=>'y',

            '&'=>'en', '@'=>'at', '#'=>'h', '$'=>'s', '%'=>'perc', '^'=>'-','*'=>'-'
        );            
      
      $XMLText = strtr($XMLText, $normalizeChars);
      
      return $XMLText;
 }
  
}//end class XMLPain

class PaymentInformation
{
    
    //<PmtInf> [1..0], PaymentInfo header 
    //PaymentInfo variables are:
    // - 2.1 <PmtInfId>, PaymentInformationIdentification
    // - 2.2 <PmtMtd>, PaymentMethod
    // - 2.3 <BtchBookg>, BatchBooking
    // - 2.4 <NbOfTxs>, NumberOfTransactions
    // - 2.5 <CtrlSum>, ControlSum
    // - 2.6 <PmtTpInf>, PaymentTypeInformation
    // - 2.18 <ReqdColltnDt>, RequestedCollectionDate
    // - 2.19 <Cdtr>, Creditor
    // - 2.20 <CdtrAcct>, CreditorAccount
    // - 2.21 <CdtrAgt>, CreditorAgent
    // - 2.22 <CdtrAgtAcct>, CreditorAgentAccount
    // - 2.23 <UltmtCdtr>, UltimateCredidor
    // - 2.24 <ChrgBr>, ChargeBearer
    // - 2.25 <ChrgsAcct>, ChargesAccount
    // - 2.26 <ChrgsAcctAgt>, ChargesAccountAgent
    // - 2.27 <CdtrSchmeId>, CreditorSchemeIdentification
    // - 2.28 <DrctDbtTxInf>, DirectDebitTransactionInformation
    
    //2.1 <PmtInfId> [1..1], PaymentInformationIdentification
    //Format: Text, Maxlenght: 35
    var $_PmtInfId;
    
    //2.2 <PmtMtd> [1..1], PaymentMethod
    //Format: allways 'DD'
    var $_PmtMtd         = 'DD';
    
    //2.3 <BtchBookg> [0..1], Batchbooking
    //Format: Either false or true
    // false: booking per transaction is requested
    // true : batch booking is requested
    var $_BtchBookg      = 'false'; 
    
    //2.4 <NbOfTxs> [0..1], NumberOfTransactions
    //Format: Max15NumericText
    var $_PmtInfNbOfTxs  = 0; 
    
    //2.5 <CtrlSum> [0..1], ControlSum
    //Format: DecimalNuber, fractionDigits:17 totalDigits: 18
    var $_PmtInfCtrlSum  = 0; 
    
    //2.6 <PmtTpInf> [0..1], PaymentTypeInformation 
    //PaymentTypeInformation variables are:
    // - <InstrPrty>, Instruction priority
    // - <SvcLvl> , Service level
    // - <LclInstrum>, LocalInstrument
    // - <SeqTp>, Sequencetype
    // - <CtgyPurp>, CategoryPurpose
    
      //2.7 <InstrPrty>
      // Not used
     
      //2.8 <SvcLvl> [0..1], 2.9 <Cd> [1..1]
      //Service Level Code
      //Format: allways 'SEPA'
      var $_SvcLvlCd       = 'SEPA';
        
      //2.11 <LclInstrm> [0..1], 2.12 <Cd> [1..1]
      //Service Level Code
      //Format: allways 'CORE'
      var $_LclInstrmCd    = 'CORE';    
    
      //2.14 <SeqTp> [0..1]
      //Must contain one of the values "FRST", "RCUR", "FNAL" or "OOFF"
      Var $_SeqTp          = 'FRST';
      
      //2.15 <CtgyPurp>
      // Not used
    
    //2.18 <ReqdColltnDt> [1..1], RequestedCollectionDate
    //Format: date
    var $_ReqdColltnDt;
   
    //2.19 <Cdtr> [1..1], Creditor
    //Creditor variables are:
    // - <Nm>, Name
    // - <PstlAdr>, PostalAddress
    // - <Id>, Identification
    // - <CtryOfRes>, CountryOfResidence 
    // - <CtctDtls>, ContactDetails
    
    
      //2.19 <Cdtr> [1..1], <Nm> [0..1], CreditorName 
      //Format: Text, MaxLength 70
      var $_CdtrNm;
       
      //2.19 <Cdtr> [1..1], <PstlAdr> [0..1]
      //Creditor PostalAddress
      //Format: Text
      var $_CdtrPstlAdr1;
      var $_CdtrPstlAdr2;
    
      //2.19 <Cdtr> [1..1], <Id> [0..1]
      //Not Used
    
      //2.19 <Cdtr> [1..1], <CtryOfRes> [0..1]
      //Creditor CountryOfResidence
      //Format: Code
      var $_CdtrCtryOfRes ='NL';
    
      //2.19 <Cdtr> [1..1], <CtctDtls> [0..1]
      //Not Used
       
    //2.20 <CdtrAcct> [1..1], <Id> [1..1]
    //CreditorAccount Identification
    //Format: IBAN, Sparta IBAN number
    var $_CdtrIBAN       = 'NL12RABO0345678912';
        
    //2.21 <CdtrAgt> [1..1], <FinInstnId> [1..1]
    //CreditorAgent, FinancialInstitutionIdentification
    //Format: BIC, Sparta Rabobank BIC code
    var $_CdtrBIC        = 'RABONL2U';  
    
    //2.22 <CdtrAgtAcct>, [0..1] CreditorAgentAccount
    //Not Used
    
    //2.23 <UltmtCdtr>, [0..1], UltimateCredidor
    //Not Used
    
    //2.24 <ChrgBr>, [0..1], ChargeBearer
    //Not Used
    
    //2.25 <ChrgsAcct>, [0..1], ChargesAccount
    //Not Used
    
    //2.26 <ChrgsAcctAgt>, [0..1], ChargesAccountAgent
    //Not Used
    
    //2.27 <CdtrSchmeId>, CreditorSchemeIdentification
    //CreditorSchemeIdentification variables are:
    // - <Nm>, Name
    // - <PstlAdr>, PostalAddress
    // - <Id>, Identification (Here mandatory for EPC)
    // - <CtryOfRes>, CountryOfResidence 
    // - <CtctDtls>, ContactDetails 
    
      //2.27 <CdtrScheId>, <Nm>
      //Used is $_CdtrNm;
      
      //2.27 <CdtrScheId>, <PstlAdr>
      //Not Used
    
      //2.27 <CdtrScheId>, <Id>, <PrvtId>, <Othr>, <Id> 
        //Definition: Number assigned by an agent to identify its customer.
        //Data Type: Max35Text
        //Format: maxLength: 35
        //minLength: 1
        var $_CstmrNb        = 'CustomerNumber';
    
     //2.27 <CdtrScheId>, <Id>, <PrvtId>, <Othr>, <SchmeNm>, <Prtry>
        //EPC: 'Scheme Name'under 'Other' must specify 'SEPA' under 'Proprietary'
        //Value allways 'SEPA'
        var $_CdtrSchmeIdPrtry        = 'SEPA'; 
    
    // - 2.28 <DrctDbtTxInf>, DirectDebitTransactionInformation
    //See class DirectDebitTransactionInformation
    
    //placeholder for DirectDebitTransactionInformation
    var $XMLDirectDebitInformationText ='';

//define a standard RequestedCollectionDate over five days    
function __construct() {
   $TodaysDate           = date('Y-m-d' , time()); 
   $TodaysTime           = date('H:i:s' , time());
   $TimeStamp            = $TodaysDate."T".$TodaysTime; //Format 2012-05-12T15:03:34 according guideline
   $DateOverFiveDaysInt  = mktime(0, 0, 0, date("m")  , date("d")+5, date("Y"));
   $DateOverFiveDays     = date('Y-m-d' , $DateOverFiveDaysInt);
   
   $this->_ReqdColltnDt = $DateOverFiveDays;
}

// property <PmtInfId>
function setPmtInfId($Value) {
       $this->_PmtInfId = substr($Value,0,34);       
}
 
// property <BtchBookg>
function setBtchBookg($Value) {
       $this->_BtchBookg = $Value;       
}
 
// property <PmtInf NbOfTxs>
function getPmtInfNbOfTxs() {
       return $this->_PmtInfNbOfTxs;       
}
 
// property <PmtInf InfCtrlSum>
function getPmtInfCtrlSum() {
       return $this->_PmtInfCtrlSum;       
}
 
// property <ReqdColltnDt>,  must be according format 2012-05-12T15:03:34
function setReqdColltnDt($Value) {
       $this->_ReqdColltnDt = $Value;       
}
 
// property <CdtrNm>
function setCdtrNm($Value) {
       $this->_CdtrNm = substr($Value,0,69);       
}
 
// property <PstlAdr1>
function setCdtrPstlAdr1($Value) {
       $this->_CdtrPstlAdr1 = $Value;       
}
 
// property <PstlAdr2>
function setCdtrPstlAdr2($Value) {
       $this->_CdtrPstlAdr2 = $Value;       
}
 
// property <CdtrCtryOfRes>
function setCdtrCtryOfRes($Value) {
       $this->_CdtrCtryOfRes = $Value;       
}
 
// property <CdtrIBAN>
function setCdtrIBAN($Value) {
       $this->_CdtrIBAN = $Value;       
}
 
// property <CdtrBIC>
function setCdtrBIC($Value) {
       $this->_CdtrBIC = $Value;       
}
 
// property <CstmrNb>
function setCstmrNb($Value) {
       $this->_CstmrNb = $Value;       
}
 
//Adds a DirectDebitTransactionInformation block in the placeholder XMLDirectDebitInformationText.
//Updates the GroupHeaderControlSum and the GroupHeaderNumberOfTransactions
function addDrctDbtTxInf($XMLDirectDebitInformationText,$InstdAmt){
    
     $this->XMLDirectDebitInformationText .=  $XMLDirectDebitInformationText;
     $this->XMLDirectDebitInformationText .= "\r\n";
     $this->_PmtInfCtrlSum += $InstdAmt; 
     $this->_PmtInfNbOfTxs += 1;
     
}
 
//return the XML text block for <DrctDbtTxInf> from the placeholder XMLDirectDebitInformationText
function getXMLDrctDbtTxInf(){
     
     return $this->XMLDirectDebitInformationText;
}

//create and return the XML PaymentInfoHeader 
function getXMLPaymentInfoHeader(){
    
    $XMLPaymentInfoHeader = <<<XMLPAYMENTINFOHEADER
    <PmtInf>
      <PmtInfId>$this->_PmtInfId</PmtInfId> 
      <PmtMtd>$this->_PmtMtd</PmtMtd> 
      <BtchBookg>$this->_BtchBookg</BtchBookg>
      <NbOfTxs>$this->_PmtInfNbOfTxs</NbOfTxs>
      <CtrlSum>$this->_PmtInfCtrlSum</CtrlSum>
      <PmtTpInf>
        <SvcLvl>
          <Cd>$this->_SvcLvlCd</Cd> 
        </SvcLvl>
        <LclInstrm>
          <Cd>$this->_LclInstrmCd</Cd>
        </LclInstrm>
        <SeqTp>$this->_SeqTp</SeqTp>
      </PmtTpInf>
      <ReqdColltnDt>$this->_ReqdColltnDt</ReqdColltnDt> 
      <Cdtr>
        <Nm>$this->_CdtrNm</Nm> 
        <PstlAdr>
          <Ctry>$this->_CdtrCtryOfRes</Ctry>
          <AdrLine>$this->_CdtrPstlAdr1</AdrLine> 
          <AdrLine>$this->_CdtrPstlAdr2</AdrLine>  
        </PstlAdr>
      </Cdtr>
      <CdtrAcct>
        <Id>
          <IBAN>$this->_CdtrIBAN</IBAN> 
        </Id>
      </CdtrAcct>
      <CdtrAgt>
        <FinInstnId>
          <BIC>$this->_CdtrBIC</BIC> 
        </FinInstnId>
      </CdtrAgt>
      <CdtrSchmeId> 
        <Id>
          <PrvtId>
            <Othr>
              <Id>$this->_CstmrNb</Id>
              <SchmeNm>
                <Prtry>$this->_CdtrSchmeIdPrtry</Prtry>
              </SchmeNm>
            </Othr>
          </PrvtId>
        </Id>                 
      </CdtrSchmeId>
XMLPAYMENTINFOHEADER;
    
    return $XMLPaymentInfoHeader;
}
 
//create and return the complete XMLPaymentInfo block
function getXMLPaymentInfo(){
    
      $XMLPaymentInfoText       = $this->getXMLPaymentInfoHeader();
      $XMLPaymentInfoText      .= "\r\n";
      $XMLPaymentInfoText      .= $this->getXMLDrctDbtTxInf();
      $XMLPaymentInfoText      .='    </PmtInf>';
      $XMLPaymentInfoText      .= "\r\n";
      
      return $XMLPaymentInfoText;
}

}//end class PaymentInformation


class DirectDebitTransactionInformation
{
//+----------------------------------------------------------------------+
//| 2.28 XML <DrctDbtTxInf> DirectDebitTransactionInformation variables  |
//+----------------------------------------------------------------------+  
    
    //2.29 <PmtId> [1..1], 2.31 <EndToEndId> [1..1]
    //PaymentIdentification, EndToEndIdentification
    //Format: Text, Maxlenght: 35
    var $_EndToEndId;   
    
    //2.44 <InstdAmt Ccy="EUR">, [1..1], InstructedAmount
    //Format: Number, eg 12.34
    var $_InstdAmt;  
     
    //iso 2.42 <MndtId>,[0..1], MandateIdentification, Text
    var $_MndtId    = 'lidmaatschap per';
    
    //iso 2.43 <DtOfSgntr>, [0..1], DateOfSignature, Date
    var $_DtOfSgntr = '2012-05-12';
    
    //2.70 <DbtrAgt> [1..1], <FinInstnId> [1..1], <BIC> [1..1]
    //DebtorAgent, FinancialInstitutionIdentification, BIC
    //Format: Debitor BIC code
    var $_DbtrBIC;   

    //2.72 <Dbtr> [1..1], <Nm> [0..1]
    //Debitor Name 
    //Format: Text, Maxlenght: 70 
    var $_DbtrNm ;  

    //2.73 <DbtrAcct> [1..1], <Id> [1..1], <IBAN> [1..1]
    //Debtor Account, Id, IBAN
    //Format: Debitor IBAN code 
    var $_DbtrIBAN;

    //2.88 <RmtInf> [0..1], <Ustrd> [0..n]
    //RemittanceInformation, Unstructured
    //Format: Text, MaxLength: 140
    var $_Description;

    // property <EndToEndId>
    function setEndToEndId($Value) {
       $this->_EndToEndId = substr($Value,0,34);       
    }
    
    // property <InstdAmt>
    function setInstdAmt($Value) {
       $this->_InstdAmt = $Value;       
    }
    
    // property <InstdAmt>
    function getInstdAmt() {
       return $this->_InstdAmt;       
    }
    
    // property <MndtId>
    function setMndtId($Value) {
       $this->_MndtId = $Value;       
    }
    
    // property <MndtId>
    function setDtOfSgntr($Value) {
       $this->_DtOfSgntr = $Value;       
    }
    
    // property <Dbtr BIC>
    function setDbtrBIC($Value) {
       $this->_DbtrBIC = $Value;       
    }
    
    // property <DbtrNm>
    function setDbtrNm($Value) {
       $this->_DbtrNm = substr($Value,0,69);       
    }
    
    // property <DbtrIBAN>
    function setDbtrIBAN($Value) {
       $this->_DbtrIBAN = $Value;       
    }
    
    // property <Description>
    function setDescription($Value) {
       $this->_Description = substr($Value,0,139);       
    }
    
    //creates and returns a DirectDebitTransactionInformation XML block
    function getXMLDirectDebitTransactionInformation(){
                      
      $XMLDirectDebitTransactionInformation = <<<XMLDDTI
      <DrctDbtTxInf>
        <PmtId>
          <EndToEndId>$this->_EndToEndId</EndToEndId>
        </PmtId>
        <InstdAmt Ccy="EUR">$this->_InstdAmt</InstdAmt>
        <DrctDbtTx>        
          <MndtRltdInf>
             <MndtId>$this->_MndtId</MndtId>
             <DtOfSgntr>$this->_DtOfSgntr</DtOfSgntr>
          </MndtRltdInf>
        </DrctDbtTx>
        <DbtrAgt>
          <FinInstnId>
             <BIC>$this->_DbtrBIC</BIC>
          </FinInstnId>
        </DbtrAgt>
        <Dbtr>
          <Nm>$this->_DbtrNm</Nm>
        </Dbtr>
        <DbtrAcct>
          <Id>
            <IBAN>$this->_DbtrIBAN</IBAN>
          </Id>
        </DbtrAcct>
        <RmtInf>
          <Ustrd>$this->_Description</Ustrd>
        </RmtInf>
      </DrctDbtTxInf>
XMLDDTI;
   
        return $XMLDirectDebitTransactionInformation;
    }
    
    
}//end class DirectDebitTransactionInformation







?>
