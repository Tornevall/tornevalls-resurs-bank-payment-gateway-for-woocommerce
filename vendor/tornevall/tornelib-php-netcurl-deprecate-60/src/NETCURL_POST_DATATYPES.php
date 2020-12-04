<?php

namespace TorneLIB;

/**
 * Class NETCURL_POST_DATATYPES Replaced by TorneLIB\Model\Type\dataType
 *
 * @package TorneLIB
 * @version 6.1.0
 * @since 6.0.0
 * @deprecated Deprecated class. Do not use.
 */
abstract class NETCURL_POST_DATATYPES
{
    /**
     * @deprecated
     */
    const DATATYPE_NOT_SET = 0;
    /**
     * @deprecated
     */
    const DATATYPE_JSON = 1;
    /**
     * @deprecated
     */
    const DATATYPE_SOAP = 2;
    /**
     * @deprecated
     */
    const DATATYPE_XML = 3;
    /**
     * @deprecated
     */
    const DATATYPE_SOAP_XML = 4;

    /**
     * @deprecated Use NETCURL_POST_DATATYPES::DATATYPE_DEFAULT
     */
    const POST_AS_NORMAL = 0;
    /**
     * @deprecated Use NETCURL_POST_DATATYPES::DATATYPE_JSON
     */
    const POST_AS_JSON = 1;
    /**
     * @deprecated Use NETCURL_POST_DATATYPES::DATATYPE_SOAP
     */
    const POST_AS_SOAP = 2;
}
