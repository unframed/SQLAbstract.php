DEPS = deps \
	deps/test-more-php \
	deps/test_sites.php \
	deps/JSONMessage.php \
	deps/WordPress

test: pull
	php test/columns.php
	php test/selectByColumn.php
	php test/selectInColumn.php
	php test/selectByKeys.php
	php test/order.php
	php test/orderBy.php
	php test/filterLike.php
	php test/whereParams.php
	php test/countStatement.php
	php test/selectStatement.php
	php test/insertStatement.php
	php test/updateStatement.php
	./press up wp
	php test/openMySQL.php
	./press down wp

pull: ${DEPS}
	cd deps/JSONMessage.php && git pull origin
	cd deps/test_sites.php && git pull origin

deps:
	mkdir -p deps

deps/test-more-php:
	svn checkout http://test-more-php.googlecode.com/svn/trunk/ deps/test-more-php

deps/JSONMessage.php:
	git clone \
		https://github.com/laurentszyster/JSONMessage.php.git \
		deps/JSONMessage.php

deps/test_sites.php:
	git clone \
		https://github.com/unframed/test_sites.php \
		deps/test_sites.php

deps/WordPress:
	git clone \
		https://github.com/WordPress/WordPress.git \
		deps/WordPress
