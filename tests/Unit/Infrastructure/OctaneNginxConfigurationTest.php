<?php

namespace Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;

class OctaneNginxConfigurationTest extends TestCase
{
    public function test_document_root_is_forwarded_to_octane_and_php_files_are_denied(): void
    {
        $configuration = file_get_contents(
            dirname(__DIR__, 3).'/docker/nginx/conf.d/octane.conf'
        );

        $this->assertIsString($configuration);
        $this->assertStringContainsString(
            "location = / {\n        try_files /not_exists @octane;\n    }",
            str_replace("\r\n", "\n", $configuration)
        );
        $this->assertStringContainsString(
            "location = /index.php {\n        return 404;\n    }",
            str_replace("\r\n", "\n", $configuration)
        );
        $this->assertStringContainsString(
            "location ~* \\.php\$ {\n        return 404;\n    }",
            str_replace("\r\n", "\n", $configuration)
        );
    }
}
