<?php

declare(strict_types=1);

namespace AndyDefer\Repository\Tests\Integration\Directives;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\ParameterRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Repository\Directives\MakeRepositoryDirective;
use AndyDefer\Repository\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class MakeRepositoryDirectiveIntegrationTest extends IntegrationTestCase
{
    private MockObject&DirectiveInteractionService $interaction;

    private MakeRepositoryDirective $directive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->interaction = $this->createMock(DirectiveInteractionService::class);
        $this->directive = new MakeRepositoryDirective($this->interaction);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_get_signature_returns_correct_signature(): void
    {
        $signature = $this->directive->getSignature();

        $this->assertStringContainsString('make-repository', $signature);
        $this->assertStringContainsString('{name', $signature);
        $this->assertStringContainsString('--force', $signature);
    }

    public function test_get_description_returns_correct_description(): void
    {
        $description = $this->directive->getDescription();

        $this->assertStringContainsString('Create a new Repository class', $description);
    }

    public function test_get_aliases_returns_correct_aliases(): void
    {
        $aliases = $this->directive->getAliases();

        $this->assertTrue($aliases->contains('repository-make'));
        $this->assertTrue($aliases->contains('create-repository'));
        $this->assertSame(2, $aliases->count());
    }

    public function test_should_boot_laravel_returns_true(): void
    {
        $this->assertTrue($this->directive->shouldBootLaravel());
    }

    public function test_execute_with_valid_repository_returns_success(): void
    {
        $arguments = new ParameterCollection;
        $arguments->add(new ParameterRecord(name: 'name', value: 'Users/UserRepository'));
        $this->directive->setArguments($arguments);

        $options = new ParameterCollection;
        $options->add(new ParameterRecord(name: 'force', value: false));
        $this->directive->setOptions($options);

        $this->interaction->expects($this->atLeastOnce())
            ->method('info');

        $result = $this->directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_without_name_returns_failure(): void
    {
        $arguments = new ParameterCollection;
        $this->directive->setArguments($arguments);

        $options = new ParameterCollection;
        $options->add(new ParameterRecord(name: 'force', value: false));
        $this->directive->setOptions($options);

        $result = $this->directive->execute();

        $this->assertSame(ExitCode::FAILURE, $result);
    }

    public function test_execute_with_force_flag_success(): void
    {
        $arguments = new ParameterCollection;
        $arguments->add(new ParameterRecord(name: 'name', value: 'Users/ExistingRepository'));
        $this->directive->setArguments($arguments);

        $options = new ParameterCollection;
        $options->add(new ParameterRecord(name: 'force', value: true));
        $this->directive->setOptions($options);

        $this->interaction->expects($this->atLeastOnce())
            ->method('info');

        $result = $this->directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_creates_repository_file(): void
    {
        $tempDir = sys_get_temp_dir().'/repository_test_'.uniqid();
        mkdir($tempDir, 0755, true);

        // Simuler app_path
        $originalAppPath = app_path();
        $this->app->instance('path', function () use ($tempDir) {
            return $tempDir;
        });

        $arguments = new ParameterCollection;
        $arguments->add(new ParameterRecord(name: 'name', value: 'TestRepository'));
        $this->directive->setArguments($arguments);

        $options = new ParameterCollection;
        $options->add(new ParameterRecord(name: 'force', value: false));
        $this->directive->setOptions($options);

        $this->interaction->expects($this->atLeastOnce())
            ->method('info');

        $result = $this->directive->execute();

        $this->assertSame(ExitCode::SUCCESS, $result);

        // Nettoyer
        if (is_dir($tempDir)) {
            $this->deleteDirectory($tempDir);
        }
    }

    private function deleteDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
