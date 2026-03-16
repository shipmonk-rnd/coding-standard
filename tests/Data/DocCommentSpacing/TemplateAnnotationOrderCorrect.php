<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Data\DocCommentSpacing;

/**
 * @template-covariant TEntity
 * @template TEntityId
 * @extends SomeInterface<TEntity, TEntityId>
 */
interface CorrectCovariantBeforeTemplate
{

}

/**
 * @template TKey
 * @template-contravariant TValue
 * @template-covariant TResult
 * @implements SomeInterface<TKey, TValue, TResult>
 */
interface CorrectMixedTemplateOrder
{

}

/**
 * @template T
 * @template-extends SomeClass<T>
 * @template-implements SomeInterface<T>
 * @extends OtherClass<T>
 * @implements OtherInterface<T>
 */
class CorrectTemplateExtendsImplements
{

}
