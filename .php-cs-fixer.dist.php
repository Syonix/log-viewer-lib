<?php

$finder = (new PhpCsFixer\Finder())
	->in(__DIR__);

return (new PhpCsFixer\Config())
	->setFinder($finder)
	->setRules([
		'@PSR12' => true,
		'array_syntax' => ['syntax' => 'short'],
		'control_structure_braces' => false,
		'new_with_parentheses' => false,
		'statement_indentation' => false,
	])
	->setIndent("\t");
