<?php

namespace EzWay\EzCommandBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

class ContentTypeCommand extends ContainerAwareCommand
{
    
    private $adminUserId = null;
    
    private $repository;
    private $userService;
    private $contentService;
    private $contentTypeService;
    
    
    /**
     *
     * {@inheritdoc}
     *
     */
    protected function configure()
    {
        $this->setName('ez:contenttype')
            ->setAliases(array('ez:ct'))
            ->setDefinition(array(
            new InputOption('admin-id', '', InputOption::VALUE_REQUIRED, 'Repository administrator user id (default:14)'),
            new InputOption('group', 'g', InputOption::VALUE_REQUIRED, 'Specify Content type group'),
            new InputOption('identifier', '', InputOption::VALUE_REQUIRED, 'Content Type identifier'),
        ))
            ->setDescription('Examine eZ Platform Repository content types.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will do something uncertain

  List content type groups
  <info>php %command.full_name%</info>              
                
  List content types within specific content type group
  <info>php %command.full_name% --group=Content</info>
   
  
EOF
);
    }
    
    protected function initServices() {
        $this->repository = $this->getContainer()->get('ezpublish.api.repository');
        $this->userService = $this->repository->getUserService();
        $this->contentService = $this->repository->getContentService();
        $this->contentTypeService = $this->repository->getContentTypeService();
    }
    
    
    /**
     *
     * {@inheritdoc}
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $outputIsVerbose = $output->isVerbose();
        $output = new SymfonyStyle($input, $output);
        
        $contentTypeGroupIdentifier = null;
        $contentTypeIdentifier = null;
        
        if (null !== $input->getOption('admin-id')) {
            $admin_id = intval($input->getOption('admin-id'));
            if ($admin_id == 0) {
                throw new \InvalidArgumentException('User id must be numeric.');
            }
            $this->adminUserId = $admin_id;
        }
        
        if (null !== $input->getOption('group')) {
            $contentTypeGroupIdentifier = $input->getOption('group');
        }
        
        if (null !== $input->getOption('identifier')) {
            $contentTypeIdentifier = $input->getOption('identifier');
        }
        
        
        // init services
        $this->initServices();
            
        // we need escalated privileges
        if ($this->adminUserId !== null) {
            $user = $this->userService->loadUser($this->adminUserId);
            $output->writeln('Operating repository as <info>' . $user->id . ':' . $user->login . '</info>');
            $this->repository->setCurrentUser($user);
        }
        
        $contentTypeGroups = $this->contentTypeService->loadContentTypeGroups();
        
        if ($contentTypeGroupIdentifier === null ) {
            $this->listContentTypeGroups($output, $contentTypeGroups);
        } else {
            if (!$this->isValidGroup($contentTypeGroupIdentifier, $contentTypeGroups))
                throw new \InvalidArgumentException('Unknow Content Type Group '. $contentTypeGroupIdentifier);
            if ($contentTypeIdentifier !== null) {
                $contentType = $this->contentTypeService->loadContentTypeByIdentifier($contentTypeIdentifier);
                $this->printContentType($output, $contentType);
            } else {
                $contentTypes = $this->contentTypeService->loadContentTypes($contentTypeGroupIdentifier);
                $this->listContentTypes($output, $contentTypeGroupIdentifier, $contentTypes);
            }
        }
        
        
        $output->success('Done');
    }
    
    private function isValidGroup($identifier, $groups) {
        foreach ($groups as $k => $group) {
            if ($identifier == $group->identifier) return true;
        }
        return false;
    }
    
    /**
     * 
     * @param OutputInterface $output
     */
    private function listContentTypeGroups(OutputInterface $output, $contentTypeGroups) {
        $output->writeln('<info>ContentType Groups:</info>');
        foreach ($contentTypeGroups as $k => $contentTypeGroup) {
            $output->writeln('' . $contentTypeGroup->identifier . '');
        }
    }
    
    
    private function printContentType(OutputInterface $output, $contentType) {
        $output->writeln('<info>' . $contentType->id . ':' . $contentType->identifier . '</info> fields:' . count($contentType->fieldDefinitions));
        $table = new Table($output);
        $table->setHeaders(array(
            'id',
            'identifier',
            'fieldType'
        ));
        foreach ($contentType->fieldDefinitions as $field) {
            $table->addRow(array(
                $field->id,
                $field->identifier,
                $field->fieldTypeIdentifier
            ));
        }
        $table->render();
    }
    

    /**
     * 
     * @param OutputInterface $output
     * @param unknown $contentTypeGroup
     */
    private function listContentTypes(OutputInterface $output, $contentTypeGroup, $contentTypes) {
        $output->writeln('<info>' . $contentTypeGroup->identifier . '</info> [ContentTypeGroup]:');
        foreach ($contentTypes as $key => $contentType) {
            $this->printContentType($output, $contentType);
        }
    }
    
    
    
}
