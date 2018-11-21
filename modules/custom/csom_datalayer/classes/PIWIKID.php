<?php

namespace Drupal\CSOM_DataLayer {


    class PIWIKID
    {

        var $Visitor_id;
        var $_pk_id_cookie;

        function __construct($cookie, $name)
        {

            if (isset($cookie[$name])) {
                $this->_pk_id_cookie = $cookie[$name];
            }
        }

        //Finds and returns the google analytics client id from the _pk_id cookie
        public function GetVisitorID()
        {

            // Parse cookie _pk_id='<visitorid>.<someth[ing>.<something>.....$_REQUEST';
            return explode('.', $this->_pk_id_cookie)[0];
        }
    }
}

?>