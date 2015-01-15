DEPS = deps \
	deps/test-more-php \
	deps/JSONMessage.php

test: pull
	php test/test_columns.php
	php test/test_selectByColumn.php
	php test/test_selectInColumn.php
	php test/test_selectByKeys.php

pull: ${DEPS}
	cd deps/JSONMessage.php && git pull origin

deps:
	mkdir -p deps

deps/test-more-php:
	svn checkout http://test-more-php.googlecode.com/svn/trunk/ deps/test-more-php

deps/JSONMessage.php:
	git clone \
		https://github.com/laurentszyster/JSONMessage.php.git \
		deps/JSONMessage.php