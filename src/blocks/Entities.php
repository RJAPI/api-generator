<?php

namespace rjapi\blocks;

use Illuminate\Database\Eloquent\SoftDeletes;
use rjapi\extension\BaseFormRequest;
use rjapi\extension\BaseModel;
use rjapi\helpers\Classes;
use rjapi\helpers\Console;
use rjapi\helpers\MethodOptions;
use rjapi\helpers\MigrationsHelper;
use rjapi\RJApiGenerator;
use rjapi\types\CustomsInterface;
use rjapi\types\DefaultInterface;
use rjapi\types\ModelsInterface;
use rjapi\types\PhpInterface;
use rjapi\types\RamlInterface;

/**
 * Class Middleware
 * @package rjapi\blocks
 * @property RJApiGenerator generator
 */
class Entities extends FormRequestModel
{
    use ContentManager, EntitiesTrait;
    /** @var RJApiGenerator $generator */
    private $generator;
    private $className;

    protected $sourceCode = '';
    protected $localCode  = '';
    protected $isSoftDelete = false;

    public function __construct($generator)
    {
        $this->generator = $generator;
        $this->className = Classes::getClassName($this->generator->objectName);
        $isSoftDelete = empty($this->generator->types[$this->generator->objectName . CustomsInterface::CUSTOM_TYPES_ATTRIBUTES]
            [RamlInterface::RAML_PROPS][ModelsInterface::COLUMN_DEL_AT]) === false;
        $this->setIsSoftDelete($isSoftDelete);
    }

    public function setCodeState($generator)
    {
        $this->generator = $generator;
    }

    /**
     * @return bool
     */
    public function isSoftDelete() : bool
    {
        return $this->isSoftDelete;
    }

    /**
     * @param bool $isSoftDelete
     */
    public function setIsSoftDelete(bool $isSoftDelete) : void
    {
        $this->isSoftDelete = $isSoftDelete;
    }

    private function setRelations()
    {
        $middlewareEntity = $this->getMiddlewareEntity($this->generator->version, $this->className);
        /** @var BaseFormRequest $middleWare **/
        $middleWare       = new $middlewareEntity();
        if(method_exists($middleWare, ModelsInterface::MODEL_METHOD_RELATIONS))
        {
            $this->sourceCode .= PHP_EOL;
            $relations = $middleWare->relations();
            foreach($relations as $relationEntity)
            {
                $ucEntitty = ucfirst($relationEntity);
                // determine if ManyToMany, OneToMany, OneToOne rels
                $current = $this->getRelationType($this->generator->objectName);
                $related = $this->getRelationType($ucEntitty);
                if(empty($current) === false && empty($related) === false)
                {
                    $this->createRelationMethod($current, $related, $relationEntity);
                }
            }
        }
    }

    /**
     * @param string $current current entity relations
     * @param string $related entities from raml file based on relations method array
     * @param string $relationEntity
     */
    private function createRelationMethod(string $current, string $related, string $relationEntity)
    {
        $ucEntitty   = ucfirst($relationEntity);
        $currentRels = explode(PhpInterface::PIPE, $current);
        $relatedRels = explode(PhpInterface::PIPE, $related);
        foreach($relatedRels as $rel)
        {
            if(strpos($rel, $this->generator->objectName) !== false)
            {
                foreach($currentRels as $cur)
                {
                    if(strpos($cur, $ucEntitty) !== false)
                    {
                        $isManyCurrent = strpos($cur, self::CHECK_MANY_BRACKETS);
                        $isManyRelated = strpos($rel, self::CHECK_MANY_BRACKETS);
                        if($isManyCurrent === false && $isManyRelated === false)
                        {// OneToOne
                            $this->setRelation($relationEntity, ModelsInterface::MODEL_METHOD_HAS_ONE);
                        }
                        if($isManyCurrent !== false && $isManyRelated === false)
                        {// ManyToOne
                            $this->setRelation($relationEntity, ModelsInterface::MODEL_METHOD_HAS_MANY);
                        }
                        if($isManyCurrent === false && $isManyRelated !== false)
                        {// OneToMany inverse
                            $this->setRelation($relationEntity, ModelsInterface::MODEL_METHOD_BELONGS_TO);
                        }
                        if($isManyCurrent !== false && $isManyRelated !== false)
                        {// ManyToMany
                            // check inversion of a pivot
                            $entityFile = $this->generator->formatEntitiesPath()
                                . PhpInterface::SLASH . $this->generator->objectName .
                                ucfirst($relationEntity) .
                                PhpInterface::PHP_EXT;
                            $relEntity  = $relationEntity;
                            $objName    = $this->generator->objectName;
                            if(file_exists($entityFile) === false)
                            {
                                $relEntity = $this->generator->objectName;
                                $objName   = $relationEntity;
                            }
                            $this->setRelation(
                                $relationEntity, ModelsInterface::MODEL_METHOD_BELONGS_TO_MANY,
                                MigrationsHelper::getTableName($objName . ucfirst($relEntity))
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * @param string $ucEntity
     */
    public function setPivot(string $ucEntity)
    {
        $file      = $this->generator->formatEntitiesPath() .
            PhpInterface::SLASH .
            $this->className . Classes::getClassName($ucEntity) . PhpInterface::PHP_EXT;
        if (true === $this->generator->isMerge) {
            $this->resetPivotContent($ucEntity, $file);
        } else {
            $this->setPivotContent($ucEntity);
        }
        $isCreated = FileManager::createFile(
            $file, $this->sourceCode,
            FileManager::isRegenerated($this->generator->options)
        );
        if($isCreated)
        {
            Console::out($file . PhpInterface::SPACE . Console::CREATED, Console::COLOR_GREEN);
        }
    }

    public function createPivot()
    {
        $middlewareEntity = $this->getMiddlewareEntity($this->generator->version, $this->className);
        /** @var BaseFormRequest $middleWare **/
        $middleWare       = new $middlewareEntity();
        if(method_exists($middleWare, ModelsInterface::MODEL_METHOD_RELATIONS))
        {
            $relations = $middleWare->relations();
            $this->sourceCode .= PHP_EOL; // margin top from props
            foreach($relations as $relationEntity)
            {
                $ucEntitty = ucfirst($relationEntity);
                $file      = $this->generator->formatEntitiesPath()
                    . PhpInterface::SLASH . ucfirst($relationEntity) . $this->generator->objectName .
                    PhpInterface::PHP_EXT;
                // check if inverse Entity pivot exists
                if(file_exists($file) === false)
                {
                    // determine if ManyToMany, OneToMany, OneToOne rels
                    $current = $this->getRelationType($this->generator->objectName);
                    $related = $this->getRelationType($ucEntitty);
                    if(empty($current) === false && empty($related) === false)
                    {
                        $this->createPivotClass($current, $related, $relationEntity);
                    }
                }
            }
        }
    }

    /**
     * @param string $current current entity relations
     * @param string $related entities from raml file based on relations method array
     * @param string $relationEntity
     */
    private function createPivotClass(string $current, string $related, string $relationEntity)
    {
        $ucEntitty   = ucfirst($relationEntity);
        $currentRels = explode(PhpInterface::PIPE, $current);
        $relatedRels = explode(PhpInterface::PIPE, $related);
        foreach($relatedRels as $rel)
        {
            if(strpos($rel, $this->generator->objectName) !== false)
            {
                foreach($currentRels as $cur)
                {
                    if(strpos($cur, $ucEntitty) !== false)
                    {
                        $isManyCurrent = strpos($cur, self::CHECK_MANY_BRACKETS);
                        $isManyRelated = strpos($rel, self::CHECK_MANY_BRACKETS);
                        if($isManyCurrent !== false && $isManyRelated !== false)
                        {// ManyToMany
                            $this->setPivot($ucEntitty);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param string    $entity
     * @param string    $method
     * @param \string[] ...$args
     */
    private function setRelation(string $entity, string $method, string ...$args)
    {
        $methodOptions = new MethodOptions();
        $methodOptions->setName($entity);
        $this->startMethod($methodOptions);
        $toReturn = $this->getRelationReturn($entity, $method, $args);
        $this->setMethodReturn($toReturn);
        $this->endMethod(1);
    }

    /**
     * @param string $entity
     * @param string $method
     * @param \string[] ...$args
     * @return string
     */
    private function getRelationReturn(string $entity, string $method, array $args)
    {
        $toReturn = PhpInterface::DOLLAR_SIGN . PhpInterface::PHP_THIS
            . PhpInterface::ARROW . $method
            . PhpInterface::OPEN_PARENTHESES . Classes::getClassName($entity)
            . PhpInterface::DOUBLE_COLON . PhpInterface::PHP_CLASS;

        if(empty($args) === false)
        {
            foreach($args as $val)
            {
                $toReturn .= PhpInterface::COMMA
                    . PhpInterface::SPACE . PhpInterface::QUOTES . $val .
                    PhpInterface::QUOTES;
            }
        }
        $toReturn .= PhpInterface::CLOSE_PARENTHESES;
        return $toReturn;
    }

    /**
     * Sets entity content to $sourceCode
     */
    private function setContent()
    {
        $this->setTag();
        $this->setNamespace(
            $this->generator->entitiesDir
        );
        $baseMapper     = BaseModel::class;
        $baseMapperName = Classes::getName($baseMapper);

        $this->setUse(SoftDeletes::class);
        $this->setUse($baseMapper, false, true);
        $this->startClass($this->className, $baseMapperName);
        $this->setUseSoftDelete();
        $this->setComment(DefaultInterface::PROPS_START);
        $this->setPropSoftDelete();
        $this->createProperty(
            ModelsInterface::PROPERTY_PRIMARY_KEY, PhpInterface::PHP_MODIFIER_PROTECTED,
            RamlInterface::RAML_ID, true
        );
        $this->createProperty(
            ModelsInterface::PROPERTY_TABLE, PhpInterface::PHP_MODIFIER_PROTECTED,
            strtolower($this->generator->objectName), true
        );
        $this->createProperty(
            ModelsInterface::PROPERTY_TIMESTAMPS, PhpInterface::PHP_MODIFIER_PUBLIC,
            PhpInterface::PHP_TYPES_BOOL_FALSE
        );
        $this->setComment(DefaultInterface::PROPS_END);
        $this->setComment(DefaultInterface::METHOD_START);
        $this->setRelations();
        $this->setComment(DefaultInterface::METHOD_END);
        $this->endClass();
    }

    /**
     * Sets entity content to $sourceCode
     */
    private function resetContent()
    {
        $this->setBeforeProps($this->getEntityFile($this->generator->formatEntitiesPath()));
        $this->setComment(DefaultInterface::PROPS_START, 0);
        $this->createProperty(
            ModelsInterface::PROPERTY_PRIMARY_KEY, PhpInterface::PHP_MODIFIER_PROTECTED,
            RamlInterface::RAML_ID, true
        );
        $this->createProperty(
            ModelsInterface::PROPERTY_TABLE, PhpInterface::PHP_MODIFIER_PROTECTED,
            strtolower($this->generator->objectName), true
        );
        $this->createProperty(
            ModelsInterface::PROPERTY_TIMESTAMPS, PhpInterface::PHP_MODIFIER_PUBLIC,
            PhpInterface::PHP_TYPES_BOOL_FALSE
        );
        $this->setAfterProps(DefaultInterface::METHOD_START);
        $this->setComment(DefaultInterface::METHOD_START, 0);
        $this->setRelations();
        $this->setAfterMethods();
    }

    /**
     *  Sets pivot entity content to $sourceCode
     * @param string $ucEntity  an entity upper case first name
     */
    private function setPivotContent(string $ucEntity)
    {
        $this->setTag();
        $this->setNamespace(
            $this->generator->entitiesDir
        );
        $baseMapper     = BaseModel::class;
        $baseMapperName = Classes::getName($baseMapper);

        $this->setUse($baseMapper, false, true);
        $this->startClass($this->className . Classes::getClassName($ucEntity), $baseMapperName);
        $this->setComment(DefaultInterface::PROPS_START);
        $this->createProperty(
            ModelsInterface::PROPERTY_PRIMARY_KEY, PhpInterface::PHP_MODIFIER_PROTECTED,
            RamlInterface::RAML_ID, true
        );
        $this->createProperty(
            ModelsInterface::PROPERTY_TABLE, PhpInterface::PHP_MODIFIER_PROTECTED,
            strtolower($this->generator->objectName . PhpInterface::UNDERSCORE . $ucEntity), true
        );
        $this->createProperty(
            ModelsInterface::PROPERTY_TIMESTAMPS, PhpInterface::PHP_MODIFIER_PUBLIC,
            PhpInterface::PHP_TYPES_BOOL_TRUE
        );
        $this->setComment(DefaultInterface::PROPS_END);
        $this->endClass();
    }

    /**
     *  Re-Sets pivot entity content to $sourceCode
     * @param string $ucEntity an entity upper case first name
     * @param string $file
     */
    private function resetPivotContent(string $ucEntity, string $file)
    {
        $this->setBeforeProps($file);
        $this->setComment(DefaultInterface::PROPS_START, 0);
        $this->createProperty(
            ModelsInterface::PROPERTY_PRIMARY_KEY, PhpInterface::PHP_MODIFIER_PROTECTED,
            RamlInterface::RAML_ID, true
        );
        $this->createProperty(
            ModelsInterface::PROPERTY_TABLE, PhpInterface::PHP_MODIFIER_PROTECTED,
            strtolower($this->generator->objectName . PhpInterface::UNDERSCORE . $ucEntity), true
        );
        $this->createProperty(
            ModelsInterface::PROPERTY_TIMESTAMPS, PhpInterface::PHP_MODIFIER_PUBLIC,
            PhpInterface::PHP_TYPES_BOOL_TRUE
        );
        $this->setAfterProps();
    }
}