<?php
/*
 * Copyright (c) 2014, Ondřej Krejčíř
 * Copyright (c) 2016, Martin Mikita
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *    * Redistributions of source code must retain the above copyright
 *      notice, this list of conditions and the following disclaimer.
 *    * Redistributions in binary form must reproduce the above copyright
 *      notice, this list of conditions and the following disclaimer in the
 *      documentation and/or other materials provided with the distribution.
 *    * Neither the name of the Ondřej Krejčíř, Martin Mikita nor the
 *      names of its contributors may be used to endorse or promote products
 *      derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL Ondřej Krejčíř, Martin Mikita BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

namespace MartinMikita;

use Nette,
    Nette\Utils\Html,
    Nette\Forms\Container;


class CheckboxTree extends Nette\Forms\Controls\MultiChoiceControl
{
    private $controlClass;

    public function __construct($label = NULL, array $items = NULL)
    {
        parent::__construct($label, $items);
        $this->control->type = 'checkbox';
        $this->controlClass = [
            'list.all' => [],
            'list.main' => [],
            'list.sub' => [],
            'item.all' => [],
            'item.main' => [],
            'item.sub' => [],
            'item.parent' => [],
            'item.main.parent' => [],
            'item.sub.parent' => [],
        ];
    }

    public function getControl()
    {
        return $this->recursiveRender($this->getItems());
    }

    public function setControlClass($type, $class)
    {
        if ($type == 'list')
            $type = 'list.all';
        if ($type == 'item')
            $type = 'item.all';
        $this->controlClass[$type] = [$class];
        return $this;
    }
    public function addControlClass($type, $class)
    {
        if ($type == 'list')
            $type = 'list.all';
        if ($type == 'item')
            $type = 'item.all';
        $this->controlClass[$type][] = $class;
        return $this;
    }

    private function getHtmlItemId($item)
    {
        return $this->getHtmlId() . '-' . str_replace(" ", "_", $item);
    }

    private function recursiveRender($list, $lvl = 0)
    {
        $ulClass = array_merge(['sub'.$lvl], $this->controlClass['list.all']);
        if ($lvl == 0) {
            $ulClass = array_merge($ulClass, $this->controlClass['list.main']);
        }
        else {
            $ulClass = array_merge($ulClass, $this->controlClass['list.sub']);
        }
        $liClass = array_merge(['sub'.$lvl], $this->controlClass['item.all']);
        if ($lvl == 0)
            $liClass = array_merge($liClass, $this->controlClass['item.main']);
        else
            $liClass = array_merge($liClass, $this->controlClass['item.sub']);

        $html = Html::el("ul", array('class'=>join(' ', $ulClass)));
        foreach ($list as $key => $value) {
            if (is_array($value)) {
                $html->add(Html::el("li", array('class'=>join(' ', $liClass)))
                        ->add($this->getControlPart($key, $lvl))
                        ->add(Html::el("label", array("for" => $this->getHtmlItemId($key)))
                            ->add($key)
                        )
                        ->add($this->recursiveRender($value, $lvl + 1))
                );
            } else {
                $liClassPar = array_merge($liClass, $this->controlClass['item.parent']);
                if ($lvl == 0)
                    $liClassPar = array_merge($liClassPar, $this->controlClass['item.main.parent']);
                else
                    $liClassPar = array_merge($liClassPar, $this->controlClass['item.sub.parent']);
                $html->add(Html::el("li", array('class'=>join(' ', $liClassPar)))
                        ->add($this->getControlPart($key, $lvl))
                        ->add(Html::el("label", array("for" => $this->getHtmlItemId($key)))
                                ->add($value)
                        )
                );
            }
        }
        return $html;
    }

    public function setValue($values)
    {
        if (is_scalar($values) || $values === NULL) {
            $values = (array) $values;
        } elseif (!is_array($values)) {
            throw new Nette\InvalidArgumentException(sprintf("Value must be array or NULL, %s given in field '%s'.", gettype($values), $this->name));
        }
        $flip = array();
        foreach ($values as $value) {
            if (!is_scalar($value) && !method_exists($value, '__toString')) {
                throw new Nette\InvalidArgumentException(sprintf("Values must be scalar, %s given in field '%s'.", gettype($value), $this->name));
            }
            $flip[(string) $value] = TRUE;
        }
        $values = array_keys($flip);
        $items = $this->items;
        $nestedKeys = array();
        array_walk_recursive($items, function($value, $key) use (&$nestedKeys) {
            $nestedKeys[] = $key;
        });
        if ($diff = array_diff($values, $nestedKeys)) {
            $range = Nette\Utils\Strings::truncate(implode(', ', array_map(function($s) { return var_export($s, TRUE); }, $nestedKeys)), 70, '...');
            $vals = (count($diff) > 1 ? 's' : '') . " '" . implode("', '", $diff) . "'";
            throw new Nette\InvalidArgumentException("Value$vals are out of allowed range [$range] in field '{$this->name}'.");
        }
        $this->value = $values;
        return $this;
    }
    
    public function getLabel($caption = NULL)
    {
        return parent::getLabel($caption)->for(NULL);
    }

    public function getControlPart($key, $lvl)
    {
        return parent::getControl()->addAttributes(array(
            'id' => $this->getHtmlItemId($key),
            'checked' => in_array($key, (array)$this->value),
            'disabled' => is_array($this->disabled) ? isset($this->disabled[$key]) : $this->disabled,
            'required' => NULL,
            'value' => $key,
            'data-level' => $lvl,
        ));
    }

    public function getLabelPart($key)
    {
        return parent::getLabel($this->items[$key])->for($this->getHtmlItemId($key));
    }

    public function getSelectedItems()
    {
        return array_intersect_key($this->recursiveJoin($this->items), array_flip($this->value));
    }

    public function getValue()
    {
        return array_values(array_intersect($this->value, array_keys($this->recursiveJoin($this->items))));
    }

    private function recursiveJoin(array $array, $arry = array())
    {
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $arry = $this->recursiveJoin($val, $arry);
            } else {
                $arry[$key] = $val;
            }
        }
        return $arry;
    }

    public static function register()
    {
        Container::extensionMethod('addCheckboxTree', function (Container $_this, $name, $label, array $items = NULL) {
            return $_this[$name] = new CheckboxTree($label, $items);
        });
    }
}