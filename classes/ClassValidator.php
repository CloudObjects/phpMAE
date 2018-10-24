<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use PHPSandbox\PHPSandbox;
use PHPSandbox\SandboxWhitelistVisitor, PHPSandbox\ValidatorVisitor;
use PhpParser\ParserFactory, PhpParser\NodeTraverser;
use CloudObjects\PhpMAE\Exceptions\PhpMAEException;

/**
 * Validates that classes fit the sandbox criteria.
 */
class ClassValidator {

  private $sandbox;
  private $whitelisted_interfaces;
  private $whitelisted_types;
  private $aliases;

  public function __construct() {
    $this->sandbox = new PHPSandbox();
    $this->sandbox->set_options(array(
      'allow_classes' => true,
      'allow_aliases' => true,
      'allow_closures' => true,
      'allow_casting' => true,
      'allow_error_suppressing' => true,
      'validate_constants' => false
    ));
    $this->sandbox->whitelist(array(
      'functions' => array(
        'strlen','strcmp','strncmp','strcasecmp','strncasecmp','each','get_class','property_exists','is_a','trigger_error','user_error','gc_collect_cycles','gc_enabled','strtotime','date','idate','gmdate','mktime','gmmktime','checkdate','time','localtime','getdate','date_create','date_create_immutable','date_create_from_format','date_create_immutable_from_format','date_parse','date_parse_from_format','date_get_last_errors','date_format','date_modify','date_add','date_sub','date_timezone_get','date_timezone_set','date_offset_get','date_diff','date_time_set','date_date_set','date_isodate_set','date_timestamp_set','date_timestamp_get','timezone_open','timezone_name_get','timezone_name_from_abbr','timezone_offset_get','timezone_transitions_get','timezone_location_get','timezone_identifiers_list','timezone_abbreviations_list','date_interval_create_from_date_string','date_interval_format','date_sunrise','date_sunset','date_sun_info','ereg','ereg_replace','eregi','eregi_replace','split','spliti','sql_regcase','libxml_use_internal_errors','libxml_get_last_error','libxml_clear_errors','libxml_get_errors','openssl_spki_new','openssl_spki_verify','openssl_spki_export','openssl_spki_export_challenge','openssl_pkey_free','openssl_pkey_new','openssl_pkey_export','openssl_pkey_get_private','openssl_pkey_get_public','openssl_pkey_get_details','openssl_free_key','openssl_get_privatekey','openssl_get_publickey','openssl_x509_read','openssl_x509_free','openssl_x509_parse','openssl_x509_checkpurpose','openssl_x509_check_private_key','openssl_x509_export','openssl_x509_fingerprint','openssl_pkcs12_export','openssl_pkcs12_read','openssl_csr_new','openssl_csr_export','openssl_csr_sign','openssl_csr_get_subject','openssl_csr_get_public_key','openssl_digest','openssl_encrypt','openssl_decrypt','openssl_cipher_iv_length','openssl_sign','openssl_verify','openssl_seal','openssl_open','openssl_pbkdf2','openssl_private_encrypt','openssl_private_decrypt','openssl_public_encrypt','openssl_public_decrypt','openssl_get_md_methods','openssl_get_cipher_methods','openssl_dh_compute_key','openssl_random_pseudo_bytes','openssl_error_string','preg_match','preg_match_all','preg_replace','preg_replace_callback','preg_filter','preg_split','preg_quote','preg_grep','preg_last_error','gzcompress','gzuncompress','gzdeflate','gzinflate','gzencode','gzdecode','zlib_encode','zlib_decode','zlib_get_coding_type','ob_gzhandler','bcadd','bcsub','bcmul','bcdiv','bcmod','bcpow','bcsqrt','bcscale','bccomp','bcpowmod','bzcompress','bzdecompress','jdtogregorian','gregoriantojd','jdtojulian','juliantojd','jdtojewish','jewishtojd','jdtofrench','frenchtojd','jddayofweek','jdmonthname','easter_date','easter_days','unixtojd','jdtounix','cal_to_jd','cal_from_jd','cal_days_in_month','cal_info','ctype_alnum','ctype_alpha','ctype_cntrl','ctype_digit','ctype_lower','ctype_graph','ctype_print','ctype_punct','ctype_space','ctype_upper','ctype_xdigit','dom_import_simplexml','filter_var','filter_var_array','filter_list','filter_id','hash','hash_hmac','hash_init','hash_update','hash_final','hash_copy','hash_algos','hash_pbkdf2','hash_equals','iconv','iconv_get_encoding','iconv_set_encoding','iconv_strlen','iconv_substr','iconv_strpos','iconv_strrpos','iconv_mime_encode','iconv_mime_decode','iconv_mime_decode_headers','json_encode','json_decode','json_last_error','json_last_error_msg','ldap_connect','ldap_close','ldap_bind','ldap_sasl_bind','ldap_unbind','ldap_read','ldap_list','ldap_search','ldap_free_result','ldap_count_entries','ldap_first_entry','ldap_next_entry','ldap_get_entries','ldap_first_attribute','ldap_next_attribute','ldap_get_attributes','ldap_get_values','ldap_get_values_len','ldap_get_dn','ldap_explode_dn','ldap_dn2ufn','ldap_add','ldap_delete','ldap_modify_batch','ldap_modify','ldap_mod_add','ldap_mod_replace','ldap_mod_del','ldap_errno','ldap_err2str','ldap_error','ldap_compare','ldap_rename','ldap_get_option','ldap_set_option','ldap_first_reference','ldap_next_reference','ldap_parse_reference','ldap_parse_result','ldap_start_tls','ldap_set_rebind_proc','ldap_escape','ldap_control_paged_result','ldap_control_paged_result_response','mb_convert_case','mb_strtoupper','mb_strtolower','mb_language','mb_internal_encoding','mb_detect_order','mb_substitute_character','mb_preferred_mime_name','mb_strlen','mb_strpos','mb_strrpos','mb_stripos','mb_strripos','mb_strstr','mb_strrchr','mb_stristr','mb_strrichr','mb_substr_count','mb_substr','mb_strcut','mb_strwidth','mb_strimwidth','mb_convert_encoding','mb_detect_encoding','mb_list_encodings','mb_encoding_aliases','mb_convert_kana','mb_encode_mimeheader','mb_decode_mimeheader','mb_convert_variables','mb_encode_numericentity','mb_decode_numericentity','mb_get_info','mb_check_encoding','mb_regex_encoding','mb_regex_set_options','mb_ereg','mb_eregi','mb_ereg_replace','mb_eregi_replace','mb_ereg_replace_callback','mb_split','mb_ereg_match','mb_ereg_search','mb_ereg_search_pos','mb_ereg_search_regs','mb_ereg_search_init','mb_ereg_search_getregs','mb_ereg_search_getpos','mb_ereg_search_setpos','mbregex_encoding','mbereg','mberegi','mbereg_replace','mberegi_replace','mbsplit','mbereg_match','mbereg_search','mbereg_search_pos','mbereg_search_regs','mbereg_search_init','mbereg_search_getregs','mbereg_search_getpos','mbereg_search_setpos','mysqli_affected_rows','mysqli_autocommit','mysqli_begin_transaction','mysqli_change_user','mysqli_character_set_name','mysqli_close','mysqli_commit','mysqli_connect','mysqli_connect_errno','mysqli_connect_error','mysqli_data_seek','mysqli_dump_debug_info','mysqli_debug','mysqli_errno','mysqli_error','mysqli_error_list','mysqli_stmt_execute','mysqli_execute','mysqli_fetch_field','mysqli_fetch_fields','mysqli_fetch_field_direct','mysqli_fetch_lengths','mysqli_fetch_all','mysqli_fetch_array','mysqli_fetch_assoc','mysqli_fetch_object','mysqli_fetch_row','mysqli_field_count','mysqli_field_seek','mysqli_field_tell','mysqli_free_result','mysqli_get_connection_stats','mysqli_get_client_stats','mysqli_get_charset','mysqli_get_client_info','mysqli_get_client_version','mysqli_get_host_info','mysqli_get_proto_info','mysqli_get_server_info','mysqli_get_server_version','mysqli_get_warnings','mysqli_init','mysqli_info','mysqli_insert_id','mysqli_kill','mysqli_more_results','mysqli_multi_query','mysqli_next_result','mysqli_num_fields','mysqli_num_rows','mysqli_ping','mysqli_poll','mysqli_prepare','mysqli_report','mysqli_query','mysqli_real_connect','mysqli_real_escape_string','mysqli_real_query','mysqli_release_savepoint','mysqli_rollback','mysqli_savepoint','mysqli_select_db','mysqli_set_charset','mysqli_stmt_affected_rows','mysqli_stmt_attr_get','mysqli_stmt_attr_set','mysqli_stmt_bind_param','mysqli_stmt_bind_result','mysqli_stmt_close','mysqli_stmt_data_seek','mysqli_stmt_errno','mysqli_stmt_error','mysqli_stmt_error_list','mysqli_stmt_fetch','mysqli_stmt_field_count','mysqli_stmt_free_result','mysqli_stmt_get_result','mysqli_stmt_get_warnings','mysqli_stmt_init','mysqli_stmt_insert_id','mysqli_stmt_more_results','mysqli_stmt_next_result','mysqli_stmt_num_rows','mysqli_stmt_param_count','mysqli_stmt_prepare','mysqli_stmt_reset','mysqli_stmt_result_metadata','mysqli_stmt_send_long_data','mysqli_stmt_store_result','mysqli_stmt_sqlstate','mysqli_sqlstate','mysqli_ssl_set','mysqli_stat','mysqli_store_result','mysqli_thread_id','mysqli_thread_safe','mysqli_use_result','mysqli_warning_count','mysqli_refresh','mysqli_escape_string','mysqli_set_opt','iterator_to_array','iterator_count','iterator_apply','pdo_drivers','simplexml_load_string','simplexml_import_dom','bin2hex','hex2bin','sleep','usleep','time_nanosleep','time_sleep_until','strptime','wordwrap','htmlspecialchars','htmlentities','html_entity_decode','htmlspecialchars_decode','get_html_translation_table','sha1','md5','crc32','phpversion','strnatcmp','strnatcasecmp','substr_count','strspn','strcspn','strtok','strtoupper','strtolower','strpos','stripos','strrpos','strripos','strrev','hebrev','hebrevc','nl2br','basename','dirname','stripslashes','stripcslashes','strstr','stristr','strrchr','str_shuffle','str_word_count','str_split','strpbrk','substr_compare','strcoll','money_format','substr','substr_replace','quotemeta','ucfirst','lcfirst','ucwords','strtr','addslashes','addcslashes','rtrim','str_replace','str_ireplace','str_repeat','count_chars','chunk_split','trim','ltrim','strip_tags','similar_text','explode','implode','join','setlocale','localeconv','nl_langinfo','soundex','levenshtein','chr','ord','parse_str','str_getcsv','str_pad','chop','strchr','sprintf','vsprintf','sscanf','parse_url','urlencode','urldecode','rawurlencode','rawurldecode','http_build_query','escapeshellcmd','escapeshellarg','rand','srand','getrandmax','mt_rand','mt_srand','mt_getrandmax','base64_decode','base64_encode','password_hash','password_get_info','password_needs_rehash','password_verify','convert_uuencode','convert_uudecode','abs','ceil','floor','round','sin','cos','tan','asin','acos','atan','atanh','atan2','sinh','cosh','tanh','asinh','acosh','expm1','log1p','pi','is_finite','is_nan','is_infinite','pow','exp','log','log10','sqrt','hypot','deg2rad','rad2deg','bindec','hexdec','octdec','decbin','decoct','dechex','base_convert','number_format','fmod','inet_ntop','inet_pton','ip2long','long2ip','microtime','gettimeofday','uniqid','quoted_printable_decode','quoted_printable_encode','convert_cyr_string','error_get_last','serialize','unserialize','highlight_string','parse_ini_string','gethostbyaddr','gethostbyname','gethostbynamel','dns_check_record','checkdnsrr','dns_get_mx','getmxrr','dns_get_record','intval','floatval','doubleval','strval','boolval','gettype','settype','is_null','is_resource','is_bool','is_long','is_float','is_int','is_integer','is_double','is_real','is_numeric','is_string','is_array','is_object','is_scalar','fnmatch','unpack','crypt','lcg_value','metaphone','ksort','krsort','natsort','natcasesort','asort','arsort','sort','rsort','usort','uasort','uksort','shuffle','array_walk','array_walk_recursive','count','end','prev','next','reset','current','key','min','max','in_array','array_search','extract','compact','array_fill','array_fill_keys','range','array_multisort','array_push','array_pop','array_shift','array_unshift','array_splice','array_slice','array_merge','array_merge_recursive','array_replace','array_replace_recursive','array_keys','array_values','array_count_values','array_column','array_reverse','array_reduce','array_pad','array_flip','array_change_key_case','array_rand','array_unique','array_intersect','array_intersect_key','array_intersect_ukey','array_uintersect','array_intersect_assoc','array_uintersect_assoc','array_intersect_uassoc','array_uintersect_uassoc','array_diff','array_diff_key','array_diff_ukey','array_udiff','array_diff_assoc','array_udiff_assoc','array_diff_uassoc','array_udiff_uassoc','array_sum','array_product','array_filter','array_map','array_chunk','array_combine','array_key_exists','pos','sizeof','key_exists','version_compare','str_rot13','wddx_serialize_value','wddx_serialize_vars','wddx_packet_start','wddx_packet_end','wddx_add_vars','xml_parser_create','xml_parser_create_ns','xml_set_object','xml_set_element_handler','xml_set_character_data_handler','xml_set_processing_instruction_handler','xml_set_default_handler','xml_set_unparsed_entity_decl_handler','xml_set_notation_decl_handler','xml_set_external_entity_ref_handler','xml_set_start_namespace_decl_handler','xml_set_end_namespace_decl_handler','xml_parse','xml_parse_into_struct','xml_get_error_code','xml_error_string','xml_get_current_line_number','xml_get_current_column_number','xml_get_current_byte_index','xml_parser_free','xml_parser_set_option','xml_parser_get_option','utf8_encode','utf8_decode','xmlwriter_open_memory','xmlwriter_set_indent','xmlwriter_set_indent_string','xmlwriter_start_comment','xmlwriter_end_comment','xmlwriter_start_attribute','xmlwriter_end_attribute','xmlwriter_write_attribute','xmlwriter_start_attribute_ns','xmlwriter_write_attribute_ns','xmlwriter_start_element','xmlwriter_end_element','xmlwriter_full_end_element','xmlwriter_start_element_ns','xmlwriter_write_element','xmlwriter_write_element_ns','xmlwriter_start_pi','xmlwriter_end_pi','xmlwriter_write_pi','xmlwriter_start_cdata','xmlwriter_end_cdata','xmlwriter_write_cdata','xmlwriter_text','xmlwriter_write_raw','xmlwriter_start_document','xmlwriter_end_document','xmlwriter_write_comment','xmlwriter_start_dtd','xmlwriter_end_dtd','xmlwriter_write_dtd','xmlwriter_start_dtd_element','xmlwriter_end_dtd_element','xmlwriter_write_dtd_element','xmlwriter_start_dtd_attlist','xmlwriter_end_dtd_attlist','xmlwriter_write_dtd_attlist','xmlwriter_start_dtd_entity','xmlwriter_end_dtd_entity','xmlwriter_write_dtd_entity','xmlwriter_output_memory','xmlwriter_flush',
        'promise\unwrap',
        'guzzlehttp\psr7\parse_header'
      )
    ));

    $this->whitelisted_interfaces = array(
      'Pimple\ServiceProviderInterface',
      'Silex\Api\ControllerProviderInterface',
      'Symfony\Component\EventDispatcher\EventSubscriberInterface',
      'Psr\Http\Message\RequestInterface',
      'CloudObjects\PhpMAE\FunctionInterface'
    );
    $this->whitelisted_types = array(
      'ArrayObject', 'DateInterval', 'DateTime', 'DateTimeImmutable', 'DateTimeZone',
      'DOMElement', 'Exception',
      'SimpleXMLElement',
      'ML\IRI\IRI',
      'ML\JsonLD\JsonLD', 'ML\JsonLD\Node',
      'GuzzleHttp\Client',
      'GuzzleHttp\HandlerStack', 'GuzzleHttp\Middleware',
      'GuzzleHttp\Handler\CurlHandler',
      'GuzzleHttp\Subscriber\Oauth\Oauth1',
      'GuzzleHttp\Promise',
      'Dflydev\FigCookies\SetCookie',
      'Symfony\Component\DomCrawler\Crawler',
      'CloudObjects\PhpMAE\ConfigLoader',
      'CloudObjects\SDK\NodeReader',
      'CloudObjects\SDK\AccountGateway\AccountContext',
      'CloudObjects\SDK\AccountGateway\AAUIDParser',
      'CloudObjects\SDK\COIDParser',
      'Defuse\Crypto\Crypto', 'Defuse\Crypto\Key',
      'gamringer\JSONPointer\Pointer', 'gamringer\JSONPointer\Exception',
    );
  }

  private function initializeWhitelist() {
    // Generate whitelist based on alias names
    $interfaces = array();
    $types = array();
    foreach ($this->whitelisted_interfaces as $i) {
      $interfaces[] = (isset($this->aliases[$i]))
        ? strtolower($this->aliases[$i]) : strtolower($i);
    }
    foreach ($this->whitelisted_types as $t) {
      $types[] = (isset($this->aliases[$t]))
        ? strtolower($this->aliases[$t]) : strtolower($t);
    }
    // Apply to sandbox
    $this->sandbox->whitelist(array(
      'interfaces' => $interfaces,
      'types' => $types,
      'classes' => $types
    ));
  }

  public function validate($sourceCode) {
    // Initialize parser and parse source code
    $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
    $ast = $parser->parse($sourceCode);

    // Parse and dump use statements
    $aliasMap = array();
    while (get_class($ast[0])=='PhpParser\Node\Stmt\Use_') {
      foreach ($ast[0]->uses as $use) {
        $name = (string)$use->name;
        $aliasMap[$use->alias] = $name;
        $this->aliases[$name] = $use->alias;
      }
      array_shift($ast);
    }
    // Check for class definition and implemented interfaces
    if (count($ast)==1 && get_class($ast[0])=='PhpParser\Node\Stmt\Class_'
        && isset($ast[0]->implements)) {

      $interfaces = array();
      foreach ($ast[0]->implements as $i) {
        $name = (string)$i;
        $interfaces[] = (isset($aliasMap[$name])) ? $aliasMap[$name] : $name;
      }
      /*if (!in_array($interface, $interfaces)) {
        // Interface not implemented
        throw new PhpMAEException("Source code file must declare a class that implements <".$interface.">.");
      } */

      // Allow self-references
      $this->whitelisted_types[] = strtolower($ast[0]->name);

      // Initialize whitelist
      $this->initializeWhitelist();

      // Apply whitelist visitor
      $traverser = new NodeTraverser;
      $traverser->addVisitor(new CustomValidationVisitor($this->sandbox));
      $traverser->addVisitor(new SandboxWhitelistVisitor($this->sandbox));
      $traverser->addVisitor(new ValidatorVisitor($this->sandbox));
      $traverser->traverse($ast);

      return;

    } else {
      // Throw exeption if conditions are not met
      throw new PhpMAEException("Source code file must include exactly one class declaration and must not contain further side effects.");
    }
  }

  public static function isInvokableClass($class) {
    return method_exists($class, '__invoke');
  }

}
