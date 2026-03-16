<?php declare(strict_types = 1);

namespace ShipMonkTests\CodingStandard\Data\DocCommentSpacing;

// line 6: IncorrectOrderOfAnnotationsInGroup (@extends before @template)
/**
 * @extends SomeInterface<T>
 * @template T
 */
interface ExtendsBeforeTemplate
{

}

// line 17: IncorrectOrderOfAnnotationsInGroup (@implements before @template)
/**
 * @implements SomeInterface<T>
 * @template T
 */
interface ImplementsBeforeTemplate
{

}

// line 28: IncorrectOrderOfAnnotationsInGroup (@implements before @extends)
/**
 * @template T
 * @implements SomeInterface<T>
 * @extends OtherInterface<T>
 */
interface ImplementsBeforeExtends
{

}

// lines 37-39: IncorrectAnnotationsGroup (blank line between annotations in same group)
/**
 * @template T
 *
 * @extends SomeInterface<T>
 */
interface BlankLineBetweenAnnotationsInSameGroup
{

}
