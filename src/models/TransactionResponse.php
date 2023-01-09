<?php
/**
 * PayPal Transparent Redirect plugin for Craft CMS 3.x
 *
 * Basic support for PayPal/Payflow Pro Transparent Redirect (SILENTTRAN).
 *
 * @link      https://www.appliedart.com
 * @copyright Copyright (c) 2022 Applied Art & Technology
 */

namespace appliedart\paypaltransparentredirect\models;

use appliedart\paypaltransparentredirect\Plugin;
use appliedart\paypaltransparentredirect\records\PaypalItemRecord;

use Craft;
use craft\base\Model;
use craft\fields\Number;

/**
 * TransactionResponse Model
 *
 * Models are containers for data. Just about every time information is passed
 * between services, controllers, and templates in Craft, it’s passed via a model.
 *
 * https://craftcms.com/docs/plugins/models
 *
 * @author    Applied Art & Technology
 * @package   PaypalTransparentRedirect
 * @since     0.1.0
 */
class TransactionResponse extends Model {
    // Public Properties
    // =========================================================================

    public $id;
    public $uid;
    public $dateCreated;
    public $dateUpdated;
    public $isComplete = FALSE;
    public $fullResponse;

    public $PNREF;
    public $PPREF;
    public $RESULT;
    public $CVV2MATCH;
    public $RESPMSG;

    public $AUTHCODE;
    public $AVSADDR;
    public $AVSZIP;
    public $IAVS;
    public $PROCAVS;
    public $PROCCVV2;

    public $HOSTCODE;
    public $RESPTEXT;
    public $PROCCARDSECURE;
    public $ADDLMSGS;
    public $PAYMENTTYPE;
    public $CORRELATIONID;
    public $AMEXID;
    public $AMEXPOSDATA;
    public $CCTRANSID;
    public $CCTRANS_POSDATA;
    public $AMT;
    public $ORIGAMT;
    public $CARDTYPE;
    public $EMAILMATCH;
    public $PHONEMATCH;
    public $EXTRSPMSG;

    public $TRANSTIME;
    public $DUPLICATE;
    public $DATE_TO_SETTLE;
    public $PAYMENTADVICECODE;
    public $TRANSSTATE;
    public $TENDER;
    public $RECURRING;
    public $ORDERID;
    public $COMMENT1;
    public $COMMENT2;
    public $CURRENCY;
    public $CUSTCODE;
    public $CUSTREF;
    public $EMAIL;
    public $INVNUM;
    public $PONUM;

    public $SECURETOKEN;
    public $SECURETOKENID;
    public $TXID;
    public $TRXTYPE;
    public $ACCT;
    public $EXPDATE;
    public $AVSDATA;

    public $NAME;
    public $FIRSTNAME;
    public $MIDDLENAME;
    public $LASTNAME;
    public $COMPANY;
    public $ADDRESS;
    public $ADDRESS2;
    public $STREET;
    public $STREET2;
    public $CITY;
    public $STATE;
    public $ZIP;
    public $COUNTRY;

    public $BILLTONAME;
    public $BILLTOFIRSTNAME;
    public $BILLTOMIDDLENAME;
    public $BILLTOLASTNAME;
    public $BILLTOCOMPANY;
    public $BILLTOSTREET;
    public $BILLTOSTREET2;
    public $BILLTOCITY;
    public $BILLTOSTATE;
    public $BILLTOZIP;
    public $BILLTOCOUNTRY;
    public $BILLTOEMAIL;
    public $BILLTOPHONENUM;

    public $SHIPTONAME;
    public $SHIPTOFIRSTNAME;
    public $SHIPTOMIDDLENAME;
    public $SHIPTOLASTNAME;
    public $SHIPTOCOMPANY;
    public $SHIPTOSTREET;
    public $SHIPTOSTREET2;
    public $SHIPTOCITY;
    public $SHIPTOSTATE;
    public $SHIPTOZIP;
    public $SHIPTOCOUNTRY;

    public $USER1;
    public $USER2;
    public $USER3;
    public $USER4;
    public $USER5;
    public $USER6;
    public $USER7;
    public $USER8;
    public $USER9;
    public $USER10;

    public function rules() {
        return [];
    }

    public function getApproved() {
        if ($this->RESULT === '0' && preg_match('/^Approved\\b/i', $this->RESPMSG)) {
            return TRUE;
        }

        return FALSE;
    }
}
