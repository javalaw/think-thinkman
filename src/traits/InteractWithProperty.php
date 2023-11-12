<?php
namespace think\thinkman\traits;

trait InteractWithProperty
{
    public function readProperty(object $obj, string|array $propertyName): string|array
    {
        if (is_string($propertyName)) {
            $propertyName = [$propertyName];
        }
        $reflection = new \ReflectionClass($obj);
        $values = [];
        foreach ($propertyName as $singlePropertyName) {
            $property = $reflection->getProperty($singlePropertyName);
            $property->setAccessible(true);
            $values[$singlePropertyName] = $property->getValue($obj);
        }
        if (is_string($values)) {
            return $values[0];
        }
        return $values;
    }
}