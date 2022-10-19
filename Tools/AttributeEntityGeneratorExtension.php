<?php

namespace Oro\Bundle\AkeneoBundle\Tools;

use Doctrine\Inflector\Inflector;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendConfigDumper;
use Oro\Bundle\EntityExtendBundle\Tools\GeneratorExtensions\AbstractEntityGeneratorExtension;
use Oro\Component\PhpUtils\ClassGenerator;

class AttributeEntityGeneratorExtension extends AbstractEntityGeneratorExtension
{
    private Inflector $inflector;

    public function __construct(Inflector $inflector)
    {
        $this->inflector = $inflector;
    }

    public function supports(array $schema): bool
    {
        return !empty($schema['attribute']);
    }

    public function generate(array $schema, ClassGenerator $class): void
    {
        foreach ($schema['attribute'] as $attributeName => $fields) {
            $this->generateBasicMethods($attributeName, $fields, $class);
            $this->generateRelationDefaultMethods($attributeName, $fields, $class);
        }
    }

    private function generateBasicMethods(string $attributeName, array $fields, ClassGenerator $class): void
    {
        $getMethodName = $this->generateGetMethodName($attributeName);
        $setMethodName = $this->generateSetMethodName($attributeName);

        $initialGetMethodBody = '';
        if ($class->hasMethod($getMethodName)) {
            $initialGetMethodBody = $class->getMethod($getMethodName)->getBody();
            $class->removeMethod($getMethodName);
        }

        $initialSetMethodBody = '';
        if ($class->hasMethod($setMethodName)) {
            $initialSetMethodBody = $class->getMethod($setMethodName)->getBody();
            $class->removeMethod($setMethodName);
        }

        $getMethodBody = '';
        $setMethodBody = '';

        foreach ($fields as $fieldName => $fieldData) {
            $getFieldMethodName = $this->generateGetMethodName($fieldName);
            if ($class->hasMethod($getFieldMethodName)) {
                $getMethodBody .= $this->generateCondition(
                    $fieldData['organization_id'],
                    \sprintf('   return $this->%s();', $getFieldMethodName)
                );
                $getMethodBody .= "\n";
            }

            $setFieldMethodName = $this->generateSetMethodName($fieldName);
            if ($class->hasMethod($setFieldMethodName)) {
                $setMethodBody .= $this->generateCondition(
                    $fieldData['organization_id'],
                    \sprintf('   return $this->%s($value);', $setFieldMethodName)
                );
                $setMethodBody .= "\n";
            }
        }

        $getMethodBody = $getMethodBody ? $getMethodBody .= 'return null;' : $initialGetMethodBody;
        if ($getMethodBody) {
            $class->addMethod($getMethodName)->addBody($getMethodBody);
        }
        $setMethodBody = $setMethodBody ? $setMethodBody .= 'return $this;' : $initialSetMethodBody;
        if ($setMethodBody) {
            $class->addMethod($setMethodName)->addBody($setMethodBody)->addParameter('value');
        }
    }

    private function generateRelationDefaultMethods(string $attributeName, array $fields, ClassGenerator $class): void
    {
        $defaultAttributeName = ExtendConfigDumper::DEFAULT_PREFIX . $attributeName;
        $getMethodName = $this->generateGetMethodName($defaultAttributeName);
        $setMethodName = $this->generateSetMethodName($defaultAttributeName);

        if ($class->hasMethod($getMethodName)) {
            $class->removeMethod($getMethodName);
        }

        if ($class->hasMethod($setMethodName)) {
            $class->removeMethod($setMethodName);
        }

        $getMethodBody = '';
        $setMethodBody = '';
        foreach ($fields as $fieldName => $fieldData) {
            $defaultFieldName = ExtendConfigDumper::DEFAULT_PREFIX . $fieldName;
            if (!$class->hasProperty($defaultFieldName)) {
                continue;
            }

            $getFieldMethodName = $this->generateGetMethodName($defaultFieldName);
            if ($class->hasMethod($getFieldMethodName)) {
                $getMethodBody .= $this->generateCondition(
                    $fieldData['organization_id'],
                    sprintf('   return $this->%s();', $getFieldMethodName)
                );
                $getMethodBody .= "\n";
            }

            $setFieldMethodName = $this->generateSetMethodName($defaultFieldName);
            if ($class->hasMethod($setFieldMethodName)) {
                $setMethodBody .= $this->generateCondition(
                    $fieldData['organization_id'],
                    sprintf('   return $this->%s($value);', $setFieldMethodName)
                );
                $setMethodBody .= "\n";
            }
        }

        if ($getMethodBody && $setMethodBody) {
            $getMethodBody .= 'return null;';
            $setMethodBody .= 'return $this;';

            $class->addMethod($getMethodName)->addBody($getMethodBody);
            $class->addMethod($setMethodName)->addBody($setMethodBody)->addParameter('value');
        }
    }

    private function generateCondition(int $organizationId, string $innerBody): string
    {
        $body = sprintf('if ($this->getOrganization() && $this->getOrganization()->getId() === %d) {', $organizationId);
        $body .= "\n";
        $body .= $innerBody;
        $body .= "\n}\n";

        return $body;
    }

    private function generateGetMethodName(string $fieldName): string
    {
        return 'get' . ucfirst($this->inflector->camelize($fieldName));
    }

    private function generateSetMethodName(string $fieldName): string
    {
        return 'set' . ucfirst($this->inflector->camelize($fieldName));
    }
}
