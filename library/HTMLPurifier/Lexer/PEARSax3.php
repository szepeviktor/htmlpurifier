<?php

require_once 'XML/HTMLSax3.php'; // PEAR
require_once 'HTMLPurifier/Lexer.php';

/**
 * Lexer that uses the PEAR package XML_HTMLSax3 to parse
 * 
 * PEAR, not suprisingly, also has a SAX parser for HTML.  I don't know
 * very much about implementation, but it's fairly well written.  However, that
 * abstraction comes at a price: performance. You need to have it installed,
 * and if the API changes, it might break our adapter. Not sure whether or not
 * it's UTF-8 aware, but it has some entity parsing trouble.
 * 
 * Quite personally, I don't recommend using the PEAR class, and the defaults
 * don't use it. The unit tests do perform the tests on the SAX parser too, but
 * whatever it does for poorly formed HTML is up to it.
 * 
 * @todo Generalize so that XML_HTMLSax is also supported.
 */

class HTMLPurifier_Lexer_PEARSax3 extends HTMLPurifier_Lexer
{
    
    /**
     * Internal accumulator array for SAX parsers.
     * @protected
     */
    var $tokens = array();
    
    function tokenizeHTML($html) {
        $html = $this->escapeCDATA($html);
        $html = $this->substituteNonSpecialEntities($html);
        $parser=& new XML_HTMLSax3();
        $parser->set_object($this);
        $parser->set_element_handler('openHandler','closeHandler');
        $parser->set_data_handler('dataHandler');
        $parser->set_escape_handler('escapeHandler');
        $parser->set_option('XML_OPTION_ENTITIES_PARSED', 1);
        $parser->parse($html);
        $tokens = $this->tokens;
        $this->tokens = array();
        return $tokens;
    }
    
    /**
     * Open tag event handler, interface is defined by PEAR package.
     */
    function openHandler(&$parser, $name, $attrs, $closed) {
        if ($closed) {
            $this->tokens[] = new HTMLPurifier_Token_Empty($name, $attrs);
        } else {
            $this->tokens[] = new HTMLPurifier_Token_Start($name, $attrs);
        }
        return true;
    }
    
    /**
     * Close tag event handler, interface is defined by PEAR package.
     */
    function closeHandler(&$parser, $name) {
        // HTMLSax3 seems to always send empty tags an extra close tag
        // check and ignore if you see it:
        // [TESTME] to make sure it doesn't overreach
        if ($this->tokens[count($this->tokens)-1]->type == 'empty') {
            return true;
        }
        $this->tokens[] = new HTMLPurifier_Token_End($name);
        return true;
    }
    
    /**
     * Data event handler, interface is defined by PEAR package.
     */
    function dataHandler(&$parser, $data) {
        $this->tokens[] = new HTMLPurifier_Token_Text($data);
        return true;
    }
    
    /**
     * Escaped text handler, interface is defined by PEAR package.
     */
    function escapeHandler(&$parser, $data) {
        if (strpos($data, '--') === 0) {
            $this->tokens[] = new HTMLPurifier_Token_Comment($data);
        }
        // CDATA is handled elsewhere, but if it was handled here:
        //if (strpos($data, '[CDATA[') === 0) {
        //    $this->tokens[] = new HTMLPurifier_Token_Text(
        //        substr($data, 7, strlen($data) - 9) );
        //}
        return true;
    }
    
}

?>