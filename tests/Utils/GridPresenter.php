<?php

declare(strict_types = 1);

namespace Tests\Utils;

use Latte\Engine;
use TwiGrid\DataGrid;
use Nette\Http\Request;
use Nette\Http\Session;
use Nette\Http\Response;
use Nette\Http\UrlScript;
use Nette\Application\Routers\SimpleRouter;
use Nette\Application\UI\Presenter as NPresenter;
use Nette\Bridges\ApplicationLatte\ILatteFactory;
use Nette\Bridges\ApplicationLatte\TemplateFactory;


class GridPresenter extends NPresenter
{

	public function __construct(DataGrid $grid)
	{
		parent::__construct();

		$this->setParent(null, 'grid');
		$this->changeAction(self::DEFAULT_ACTION);

		$httpResponse = new Response;
		$httpRequest = new Request(new UrlScript('https://kesspess.cz/twigrid/'));

		$this->injectPrimary(
			null,
			null,
			new SimpleRouter,
			$httpRequest,
			$httpResponse,
			new Session($httpRequest, $httpResponse),
			null,
			new TemplateFactory(new class implements ILatteFactory {

				public function create(): Engine
				{
					return new Engine;
				}

			}, $httpRequest)
		);

		$this->addComponent($grid, 'grid');

		$this->saveGlobalState(); // intentionally due to private $globalParams "initialization" (array needed)
	}

}
