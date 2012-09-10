<?php

namespace RomaricDrigon\MetaYaml;

use RomaricDrigon\MetaYaml\SchemaValidator;
use RomaricDrigon\MetaYaml\Loader\JsonLoader;

class MetaYaml
{
    private $schema;
    private $prefix = '_';

    // need to have a schema array
    public function __construct(array $schema, $validate = false)
    {
        $this->schema = $schema;

        if (isset($this->schema['prefix'])) {
            $this->prefix = $this->schema['prefix'];
        }

        if ($validate) {
            // we validate the schema using the meta schema, defining the structure of our schema
            try {
                $this->validateSchema();
            } catch (\Exception $e) {
                throw new \Exception("Unable to validate schema with error: {$e->getMessage()}");
            }
        }
    }

    // validate the schema
    // for big files (more than a few hundred lines)
    // can take up to a second
    public function validateSchema()
    {
        $meta_schema_validator = new SchemaValidator();
        $json_loader = new JsonLoader();

        // we have to check if we use a prefix
        $meta_json = file_get_contents(__DIR__.'/../../../data/MetaSchema.json');
        $meta_json = str_replace('#', $this->prefix, $meta_json);

        // we validate the schema using the meta schema, defining the structure of our schema
        $meta_schema_validator->validate($json_loader->load($meta_json), $this->schema);

        return true;
    }

    // get the validated schema
    public function getSchema()
    {
        return $this->schema;
    }

    // validate some data array
    public function validate(array $data)
    {
        $data_validator = new SchemaValidator();

        return $data_validator->validate($this->schema, $data);
    }

    // get the documentation
    public function getDocumentationForNode(array $keys = array())
    {
        $node = $this->findNode($this->schema['root'], $keys, $is_choice);

        return array(
            'name' => end($keys) ?: 'root',
            ($is_choice ? 'choices' : 'node') => $node,
            'prefix' => $this->prefix,
            'partials' => isset($this->schema['partials']) ? $this->schema['partials'] : array()
        );
    }
    private function findNode(array $array, array $keys, &$is_choice)
    {
        // first, if it's a partial, let's naviguate
        if (isset($array[$this->prefix.'type']) && $array[$this->prefix.'type'] === 'partial') {
            $p_name = $array[$this->prefix.'partial'];

            if (! isset($this->schema['partials']) || ! isset($this->schema['partials'][$p_name])) {
                throw new \Exception("You're using a partial but partial '$p_name' is not defined in your schema");
            }

            return $this->findNode($this->schema['partials'][$p_name], $keys, $is_choice);
        }

        if ($keys === array()) {
            return $array;
        }

        if (isset($array[$this->prefix.'type'])) {
            switch ($array[$this->prefix.'type']) {
                case 'prototype': //we have to ignore one key
                    array_shift($keys);
                    return $this->findNode($array[$this->prefix.'prototype'], $keys, $is_choice);
                case 'array': // let's check the children
                    foreach ($array[$this->prefix.'children'] as $name => $child) {
                        if ($name == $keys[0]) {
                            array_shift($keys);
                            return $this->findNode($child, $keys, $is_choice);
                        }
                    }
                    break;
                case 'choice': // choice, return an array of possibilities
                    $choices = array();
                    foreach ($array[$this->prefix.'choices'] as $name => $choice) {
                        try {
                            $is_choice = true; // $is_choice is a reference, modify it here
                            $choices[$name] = $this->findNode($choice, $keys, $is_choice);
                        } catch (\Exception $e) {} // exception = invalid choice, so skip it
                    }
                    return $choices;
            }
        }

        throw new \Exception("Unable to find child {$keys[0]}");
    }
}
