<?php
/**
 *
 * @package Pandra
 */
class PandraValidator {

    // primitives for which there is self::check() logic
    static public $_primitive = array(
                                    'notempty',
                                    'int',
                                    'float',
                                    'numeric',
                                    'string',
                                    'bool',
                                    'maxlength',	// =[length]
                                    'minlength',	// =[length]
				    'enum',		// =[comma,delimitered,enumerates]
                                    'email',
                                    'url'
                                );

    /**
     * Complex types are aggregates of the predefined primitive type definitions. Similarly,
     * the type definitions can also be aggregated to build even more complex types. In cases where there appears to be
     * collision between types (aggregate types with different maxlength options for example) the first type will be
     * viewed as authoritive.
     */
    static public $_complex = array(
                                    'stringregular' => array('string', 'notempty'),
                                    'string20' => array('stringregular', 'maxlength=20'),
                                );

    /**
     * given a typedef array, detects complex types and expands to primitives
     * @param array &$typeDefs validating type definitions
     */
    static private function typeExpander(&$typeDefs) {

        $isComplex = FALSE;

        foreach ($typeDefs as $idx => $typeDef) {

        // check if type is complex
            if (array_key_exists($typeDef, self::$_complex)) {

            // drop this complex type from our typeDefs, ready to expand
                unset($typeDefs[$idx]);

                // merge against complex type def
                $typeDefs = array_merge($typeDefs, self::$_complex[$typeDef]);

                // if it looks like this type has expanded to another complex type, then flag for recursion
                foreach ($typeDefs as $xType) {
                    if (array_key_exists($xType, self::$_complex)) {
                        $isComplex = TRUE;
                    }
                }
            }
        }

        // recurse, expand out new complex type
        if ($isComplex) self::typeExpander($typeDefs);
    }


    /**
     * Validates a field
     * @param string $errorMsg custom error message for field validation error
     * @return bool field validated correctly
     */
    static public function check($value, $label, $typeDefs = array(), &$errorMsg = "") {

        if (empty($typeDefs)) return TRUE;

        if (!is_array($typeDefs)) $typeDefs = array($typeDefs);

        // normalise to real type defs if complex types found
        self::typeExpander($typeDefs);

        $error = FALSE;

        foreach ($typeDefs as $type) {

            if (preg_match('/=/', $type)) {
                list($type, $args) = explode("=", $type);
            }

		if (!in_array($type, self::$_primitive)) {
                    throw new RuntimeException("undefined type definition ($type)\n");
		}


            // check for basic validator types
            switch ($type) {
                case 'notempty' :
                    $error = empty($value);
                    if ($error) $errorMsg = "Field cannot be empty";
                    break;
                case 'email' :
                    $error = !filter_var($value, FILTER_VALIDATE_EMAIL);
                    if ($error && empty($errorMsg)) $errorMsg = "Invalid email address\n";
                    break;
                case 'url' :
                    $error = !filter_var($value, FILTER_VALIDATE_URL);
                    if ($error && empty($errorMsg)) $errorMsg = "Invalid URL\n";
                    break;
                case 'int' :
                case 'float' :
                case 'numeric' :
                case 'string' :
                case 'bool' :
                    eval('$error != is_'.$type.'("'.$value.'");');
                    if ($error && empty($errorMsg)) $errorMsg = "Field error, expected ".$type."\n";
                    break;
                case 'maxlength' :
                    if (empty($args)) throw new RuntimeException("type $type requires argument\n");
                    $error = (strlen($value) > $args);
                    if ($error) $errorMsg .= "Maximum length $args exceeded ($label)";
		    break;
                case 'minlength' :
                    if (empty($args)) throw new RuntimeException("type $type requires argument\n");
                    $error = (strlen($value) < $args);
                    if ($error) $errorMsg .= "Minimum length $args unmet ($label)\n";
                    break;
                case 'enum' :
                    if (empty($args)) throw new RuntimeException("type $type requires argument\n");
		    $enums = explode(",", $args);
		    $error = (!in_array($value, $enums));
                    if ($error) $errorMsg .= "Invalid Argument\n";
                default :
                    throw new RuntimeException("unhandled type definition ($type)\n");
                    break;
            }
        }

        return !$error;
    }
}
