<?php

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;
use Statamic\Fieldtypes\Assets\Assets;
use Statamic\Fieldtypes\Entries;
use Statamic\Query\OrderedQueryBuilder;


/**
 *  The modifier main class
 */
class StripIt extends Modifier {

    public function index($value, $params, $context) {

        // Params for specific things to keep => [] and remove => []
        $params = [
            'remove' => explode(',', isset($params[0]) ?? ''),
            'keep' => explode(',', isset($params[1]) ?? '')
        ];

        return new StripCollection($value, $params);
    }
}


/**
 * Holds all the stripped items
 */
class StripCollection {

    public $items = [];
    private $params;

    function __construct($value, $params) {
        $this->params = $params;
        $this->newStripper($value);
    }

    private function newStripper($value) {

        // Find out if its an Entry or Asset or something else
        $type = class_basename($value);

        // Only proceed to try stripping certain things. Everything else remains untouched.
        switch ($type) {

            case 'Entry':
                $this->items[] = new EntryStripper($value, $this->params);
                break;

            case 'Asset':
                $this->items[] = new AssetStripper($value, $this->params);
                break;
            case 'OrderedQueryBuilder':
                foreach ($value->get() as $item) {
                    $this->newStripper($item);
                }
                break;

            default:
                $this->items[] = $value;
                break;
        }
    }
}


/**
 * Parent for all stripped items
 */
class Stripper {

    /* Public vars will dynamically set */
    protected $original;
    protected $keepItOn;

    function __construct($value, $params) {

        $this->original = $value;

        // $keepItOn = allFields - ( fieldsToKeep - fieldsToRemove )
        $keep = $params['keep'] ?? [];
        $remove = $params['remove'] ?? [];
        $keepItOn = array_merge($keep, $this->whatToKeep());
        $takeItOff = array_merge($remove, $this->whatToRemove());
        $this->keepItOn = array_diff($keepItOn, $takeItOff);

        $this->strip();
    }

    protected function whatToKeep() {
        // Fields to keep by default
        return [];
    }

    protected function whatToRemove() {
        // Fields to remove by default
        return ['parent'];
    }

    protected function strip() {
        // $stripped = allFields - fieldsToRemove
        $this->stripped = array_intersect_key($this->original->toAugmentedArray(), array_flip($this->keepItOn));
    }

    protected function hasChildren($data) {
        // Try to decice if we should go deeper
        $type = $data->fieldtype();
        $hasMore = (bool) $data->raw();

        if ($hasMore) {
            if ($type instanceof Entries) return true;
            if ($type instanceof Assets) return true;
            if ($type instanceof OrderedQueryBuilder) return true;
        } else return false;
    }
}


/**
 * Strips Assets
 */
class AssetStripper extends Stripper {

    protected function whatToKeep() {
        // Default fields to keep for Assets
        return ['url', 'title', 'alt'];
    }

    protected function strip() {
        // $stripped = allFields - fieldsToRemove
        $stripped = array_intersect_key($this->original->toAugmentedArray(), array_flip($this->whatToKeep()));

        // Set class variables ($name = handle)
        foreach ($stripped as $name => $value) {
            $this->$name = $value->value();
        }
    }
}


/**
 * Strips Entrys
 */
class EntryStripper extends Stripper {

    protected function whatToKeep() {
        // Default fields to keep for Entries (extracted from the Entry blueprint)
        return array_keys($this->original->blueprint->fields()->all()->toArray());
    }

    protected function strip() {
        // $stripped = allFields - fieldsToRemove
        $stripped = array_intersect_key($this->original->toAugmentedArray(), array_flip($this->keepItOn));

        // Loop remaining fields and look for children to strip
        foreach ($stripped as $name => $data) {
            if ($this->hasChildren($data)) {
                $this->$name = new StripCollection($data->value(), ['keep' => $this->keepItOn]);
            } elseif ($data->raw()) {
                $this->$name = $data->value();
            }
        }
    }
}
