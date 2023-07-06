<?php

use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Fixer for using prettier-php to fix.
 */
final class PrettierPHPFixer implements FixerInterface
{
    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        // should be absolute first
        return 999;
    }
    /**
     * Returns the description of the fixer.
     *
     * @return FixerDefinitionInterface
     */
    public function getDefinition(): FixerDefinitionInterface
    {
        // Example: Return a basic definition
        return new FixerDefinition('Fixer description.', []);
    }
    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens): bool
    {
        return true;
    }
    /**
     * {@inheritdoc}
     */
    public function isRisky(): bool
    {
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function fix(SplFileInfo $file, Tokens $tokens): void
    {
        if (
            0 < $tokens->count() &&
            $this->isCandidate($tokens) &&
            $this->supports($file)
        ) {
            $this->applyFix($file, $tokens);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'Prettier/php';
    }
    /**
     * {@inheritdoc}
     */
    public function supports(SplFileInfo $file): bool
    {
        return true;
    }
    /**
     * {@inheritdoc}
     */
    private function applyFix(SplFileInfo $file, Tokens $tokens)
    {
        $tmpFile = $this->getTmpFile($file);
        exec("npm exec -- prettier --write --brace-style=1tbs $tmpFile");
        $content = file_get_contents($tmpFile);
        $tokens->setCode($content);
        (new Filesystem())->remove($tmpFile);
    }
    /**
     * Create a Temp file with the same content as given file.
     *
     * @param SplFileInfo $file file to be copied
     *
     * @return string tmp file name
     */
    private function getTmpFile(SplFileInfo $file): string
    {
        $fileSys = new Filesystem();
        $tmpFolderPath = __DIR__ . '/tmp';
        $fileSys->mkdir($tmpFolderPath);
        $tmpFileName = str_replace(
            DIRECTORY_SEPARATOR,
            '_',
            $file->getRealPath()
        );
        $tmpFilePath = $tmpFolderPath . '/__' . $tmpFileName;
        $fileSys->copy($file->getRealPath(), $tmpFilePath, true);
        return $tmpFilePath;
    }
}
