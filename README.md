# TwiGrid

... is a DataGrid for Nette Framework.

[![Buy me a Coffee](https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=5UZMKSVARNKJL)

**Demo**: https://kesspess.cz/twigrid/  
**Demo sources**: https://github.com/uestla/twigrid-demo

It's based on another (and great) datagrid written by @hrach - https://github.com/nextras/datagrid. My datagrid is hugely inspired by this component and its programming ideas and therefore I would like to give this man a maximum credit and respect :-)


# Quickstart

Let's see how many steps do we have to make to create our first datagrid.

1. ### Create new project

	```bash
	composer create-project nette/web-project twigrid-quickstart
	```

2. ### Install TwiGrid & client-side assets

	```bash
	cd twigrid-quickstart
	composer require uestla/twigrid
	yarn add twigrid-datagrid --modules-folder www/vendor
	```

	> If you're not using yarn, you can install assets manually by looking into `package.json` and see required dependencies there.

	We'll then update `app/Presenters/templates/@layout.latte` to load downloaded assets:

	```html
	<!-- app/Presenters/templates/@layout.latte -->

	<link rel="stylesheet" href="{$basePath}/vendor/bootstrap/dist/css/bootstrap.min.css">
	<link rel="stylesheet" href="{$basePath}/vendor/twigrid-datagrid/assets/twigrid.datagrid.css">
	<!-- ... -->
	<script src="{$basePath}/vendor/jquery/dist/jquery.min.js"></script>
	<script src="{$basePath}/vendor/bootstrap/dist/js/bootstrap.min.js"></script>
	<script src="{$basePath}/vendor/nette-forms/src/assets/netteForms.min.js"></script>
	<script src="{$basePath}/vendor/nette.ajax.js/nette.ajax.js"></script>
	<script src="{$basePath}/vendor/twigrid-datagrid/assets/twigrid.datagrid.js"></script>
	```

3. ### Database

	Download [the SQLite3 file](https://github.com/uestla/twigrid-demo/raw/455d55d2e2a34bae9aaa64658bf8a4b6ddfca4a0/app/users.s3db) from the demo application and place it in `app/Model/users.s3db`.

	And we'll configure this database to be used by the application:

	```neon
	# config/local.neon
	database:
		dsn: 'sqlite:%appDir%/Model/users.s3db'
	```

4. ### Create datagrid

	Now it's finally time to create our first datagrid - let's create an `app/Grids/UsersGrid.php` file. We'll need database connection for data loading, so we inject it properly via constructor.

	```php
	// app/Grids/UsersGrid.php

	final class UsersGrid extends TwiGrid\DataGrid
	{
		private $database;

		public function __construct(Nette\Database\Explorer $database)
		{
			parent::__construct();

			$this->database = $database;
		}

		protected function build(): void
		{
			// TODO
		}
	}
	```

	We'll define the datagrid body inside the `build()` method. Although the table `user` has many columns, we'll have just some of them in our grid just to make it easy.

	```php
	// app/Grids/UsersGrid.php

	final class UsersGrid extends TwiGrid\DataGrid
	{
		// ...

		protected function build(): void
		{
			$this->addColumn('firstname', 'Firstname');
			$this->addColumn('surname', 'Surname');
			$this->addColumn('streetaddress', 'Street address');
			$this->addColumn('city', 'City');
			$this->addColumn('country_code', 'Country');
		}
	}
	```

	TwiGrid also needs to know what column(s) it should consider as a primary key:

	```php
	$this->setPrimaryKey('id');
	```

	And finally we'll tell TwiGrid how to load our users:

	```php
	$this->setDataLoader(function () {
		return $this->database->table('user');
	});
	```

5. To properly inject our grid into presenters, we'll need to create a factory interface:

	```php
	// app/Grids/UsersGridFactory.php

	interface UsersGridFactory
	{
		public function create(): UsersGrid;
	}
	```

	This interface will now be used for automatic factory generation, which is handy - we simply add this definition to `config/common.neon`:

	```neon
	services:
		- implement: UsersGridFactory
	```

6. Having all of this done, we can now simply inject our grid factory into `HomepagePresenter`.

	```php
	// app/Presenters/HomepagePresenter.php

	class HomepagePresenter extends BasePresenter
	{
		/** @var \UsersGridFactory @inject */
		public $usersGridFactory;
	}
	```

	Now we'll add the control factory itself:

	```php
	// app/Presenters/HomepagePresenter.php

	protected function createComponentUsersGrid(): \UsersGrid
	{
		return $this->usersGridFactory->create();
	}
	```

7. We're nearly done! Just open `app/Presenters/templates/Homepage/default.latte`, delete the whole content and replace it with

	```latte
	{block content}
		{control usersGrid}
	{/block}
	```

	That's all, folks!

	Now when you'll open the page, you might see something like this:

	![Result screenshot](https://i.imgur.com/dlyGbyo.png)

8. Final improvement

	Maybe showing the country code isn't that sexy - we'd like to have the whole country name in "Country" column. To achieve that, we'll create custom grid template:

	```latte
	{* app/Grids/UsersGrid.latte *}

	{extends $defaultTemplate}

	{define body-cell-country_code}
		<td>{$record->country->title}</td>
	{/define}
	```

	And tell TwiGrid to use this template:

	```php
	// app/Grids/UsersGrid.php::build()

	$this->setTemplateFile(__DIR__ . '/UsersGrid.latte');
	```

	Simple as that!

More
----

To see more examples, please visit the [demo page](https://kesspess.cz/twigrid/). Enjoy!
