<?php

declare(strict_types = 1);

namespace Tests\UI;

use Latte\Engine;
use TwiGrid\DataGrid;
use Nette\Http\Request;
use Nette\Http\Session;
use Nette\Http\Response;
use Nette\Http\IRequest;
use Nette\Http\UrlScript;
use Nette\Application\UI\Presenter;
use Nette\Application\PresenterFactory;
use Nette\Application\Routers\SimpleRouter;
use Nette\Bridges\FormsLatte\FormsExtension;
use Nette\Bridges\ApplicationLatte\ILatteFactory;
use Nette\Bridges\ApplicationLatte\TemplateFactory;


/** @template T */
final class GridPresenter extends Presenter
{

	/** @param  DataGrid<T> $grid */
	public function __construct(DataGrid $grid)
	{
		parent::__construct();

		$this->setParent(null, 'grid');
		$this->changeAction('default');

		$httpResponse = new Response;
		$httpRequest = new Request(new UrlScript('https://kesspess.cz/twigrid/'));
		$router = new SimpleRouter;
		$session = new Session($httpRequest, $httpResponse);

		$templateFactory = new TemplateFactory(new class implements ILatteFactory {
			public function create(): Engine
			{
				$latte = new Engine;
				if (method_exists($latte, 'addExtension') && class_exists(FormsExtension::class)) {
					$latte->addExtension(new FormsExtension);
				}

				return $latte;
			}
		}, $httpRequest);

		// nette/application 3.1 vs 3.2 - see https://github.com/nette/application/commit/bb8f93c60f9e747530431eef75df8b0aa8ab9d5b
		$firstInjectPrimaryArgumentType = (new \ReflectionMethod(Presenter::class, 'injectPrimary'))
			->getParameters()[0]->getType();

		$newInjectPrimary = $firstInjectPrimaryArgumentType !== null
			&& @(string) $firstInjectPrimaryArgumentType === IRequest::class; // @ - ReflectionType::__toString() deprecated in PHP 7.4

		if ($newInjectPrimary) {
			$this->injectPrimary($httpRequest, $httpResponse, new PresenterFactory, $router, $session, null, $templateFactory);

		} else {
			$this->injectPrimary(null, null, $router, $httpRequest, $httpResponse, $session, null, $templateFactory);
		}

		$this->addComponent($grid, 'grid');

		$this->saveGlobalState(); // intentionally due to private $globalParams "initialization" (array needed)
	}

}
