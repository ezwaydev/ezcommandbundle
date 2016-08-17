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
    
    const CMD_ACTION_VIEW = 1;
    
    private $adminUserId = 14;
    
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
            new InputOption('list', '', InputOption::VALUE_OPTIONAL, 'List content types within repository'),
        ))
            ->setDescription('Manage eZ Platform Repository content types.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command will do something uncertain

  List content type groups
  <info>php %command.full_name% --list</info>              
                
  List content types within specific content type group
  <info>php %command.full_name% --list --group=Content</info>
   
  
EOF
);
    }
    
    protected function initServices() {
        $this->repository = $this->getContainer()->get('ezpublish.api.repository');
        $this->userService = $this->repository->getUserService();
        $this->contentService = $this->repository->getContentService();
        $this->contentTypeService = $this->repository->getContentTypeService();
        
        $this->fs = $this->getContainer()->get('filesystem');
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
        
        // default content type group
        $contentTypeGroupIdentifier = null;
        
        $action = self::CMD_ACTION_VIEW;
        
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
        
        if (null !== $input->getOption('list')) {
            $output->writeln('list:' . $input->getOption('list'));
            $action = self::CMD_ACTION_VIEW;
        }
        
        
        // init services
        $this->initServices();
        
        // we need escalated privileges
        $user = $this->userService->loadUser( $this->adminUserId );
        $output->writeln('Operating repository as <info>'. $user->id . ':' . $user->login . '</info>');
        $this->repository->setCurrentUser($user);
        
        $contentTypeGroups = $this->contentTypeService->loadContentTypeGroups();
        
        if ($contentTypeGroupIdentifier === null ) {
            $this->listContentTypeGroups($output);
        } else {
            if (!in_array($contentTypeGroupIdentifier, $contentTypeGroups))
                throw new \InvalidArgumentException('Unknow Content Type Group '. $contentTypeGroupIdentifier);
            $this->listContentTypes($output, $contentTypeGroupIdentifier);
        }
        
        
        $output->success('Done');
    }
    
    /**
     * 
     * @param OutputInterface $output
     */
    private function listContentTypeGroups(OutputInterface $output) {
        $output->writeln('<info>ContentType Groups:</info>');
        foreach ($contentTypeGroups as $k => $contentTypeGroup) {
            $output->writeln('<info>' . $contentTypeGroup->identifier . '</info>');
        }
    }

    /**
     * 
     * @param OutputInterface $output
     * @param unknown $contentTypeGroup
     */
    private function listContentTypes(OutputInterface $output, $contentTypeGroup) {
        $output->writeln('<info>' . $contentTypeGroup->identifier . '</info> [ContentTypeGroup]:');
        $contentTypes = $this->contentTypeService->loadContentTypes($contentTypeGroup);
        foreach ($contentTypes as $key => $contentType) {
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
    }
    
    
    
}
