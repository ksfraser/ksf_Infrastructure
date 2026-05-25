<?php

declare(strict_types=1);

namespace Ksfraser\Tests\Unit\FACRM;

use PHPUnit\Framework\TestCase;

class PageStructureTest extends TestCase
{
    private string $moduleDir;
    
    protected function setUp(): void
    {
        $this->moduleDir = dirname(__DIR__, 2);
    }
    
    public function testIndexPageExists(): void
    {
        $this->assertFileExists($this->moduleDir . '/pages/index.php');
    }
    
    public function testIndexPageContainsCRMUI(): void
    {
        $content = file_get_contents($this->moduleDir . '/pages/index.php');
        
        $this->assertStringContainsString('FA_CRM', $content);
        $this->assertStringContainsString('id="FA_CRM-ui"', $content);
    }
    
    public function testIndexPageUsesAppClass(): void
    {
        $content = file_get_contents($this->moduleDir . '/pages/index.php');
        
        $this->assertStringContainsString('new \ksf\App()', $content);
    }
    
    public function testIndexPageRequiresAutoload(): void
    {
        $content = file_get_contents($this->moduleDir . '/pages/index.php');
        
        $this->assertStringContainsString('autoload.php', $content);
    }
    
    public function testDatabaseServiceExists(): void
    {
        $this->assertFileExists($this->moduleDir . '/includes/ksf_FA_CRMDB.php');
    }
    
    public function testDatabaseServiceContainsClass(): void
    {
        $content = file_get_contents($this->moduleDir . '/includes/ksf_FA_CRMDB.php');
        
        $this->assertStringContainsString('class DatabaseService', $content);
        $this->assertStringContainsString('getInstance', $content);
    }
    
    public function testDatabaseServiceUsesNamespaceKsf(): void
    {
        $content = file_get_contents($this->moduleDir . '/includes/ksf_FA_CRMDB.php');
        
        $this->assertStringContainsString('namespace ksf', $content);
    }
    
    public function testImportFileExists(): void
    {
        $this->assertFileExists($this->moduleDir . '/includes/import.php');
    }
    
    public function testProjectDcsExists(): void
    {
        $this->assertFileExists($this->moduleDir . '/ProjectDcs');
    }
}
