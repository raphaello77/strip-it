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

        // set user input for keep => [] and remove => []
        $params = [
            'remove'=> explode(',', $params[0]),
            'keep' => explode(',', $params[1])
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

        $type = class_basename($value);

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

    /* public vars will dynamically set */
    protected $original;
    protected $keepItOn;

    function __construct($value, $params) {

        $this->original = $value;
        $keep = $params['keep'] ?? [];
        $remove = $params['remove'] ?? [];
        $keepItOn = array_merge($keep, $this->whatToKeep());
        $takeItOff = array_merge($remove, $this->whatToRemove());
        $this->keepItOn = array_diff($keepItOn, $takeItOff);
        $this->strip();
    }

    protected function whatToKeep() {
        return [];
    }

    protected function whatToRemove() {
        return ['parent'];
    }

    protected function strip() {
        $this->stripped = array_intersect_key($this->original->toAugmentedArray(), array_flip($this->keepItOn));
    }

    protected function hasChildren($data) {

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
        return ['url', 'title', 'alt'];
    }

    protected function strip() {

        $stripped = array_intersect_key($this->original->toAugmentedArray(), array_flip($this->whatToKeep()));

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
        // keep the blueprint fields
        return array_keys($this->original->blueprint->fields()->all()->toArray());
    }

    protected function strip() {

        $stripped = array_intersect_key($this->original->toAugmentedArray(), array_flip($this->keepItOn));

            foreach ($stripped as $name => $data) {
                if ($this->hasChildren($data)) {
                $this->$name = new StripCollection($data->value(), ['keep' => $this->keepItOn]);
                } elseif ($data->raw()) {
                    $this->$name = $data->value();
            }
        }
    }
}
