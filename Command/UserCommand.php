<?php

namespace EzWay\EzCommandBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

/**
 * Simple command line user management for eZ Platform Repository
 * @author bedo
 *
 */
class UserCommand extends ContainerAwareCommand
{
    
    const CMD_ACTION_VIEW = 1;
    const CMD_ACTION_UPDATE = 2;

    private $adminUserId = 14;
    
    private $repository;
    private $userService;
    

    /**
     *
     * {@inheritdoc}
     *
     */
    protected function configure()
    {
        $this->setName('ez:user')
            ->setDefinition(array(
            new InputOption('admin-id', '', InputOption::VALUE_REQUIRED, 'Repository administrator user id'),
            new InputOption('user-id', '', InputOption::VALUE_REQUIRED, 'User id'),
            new InputOption('user-login', '', InputOption::VALUE_REQUIRED, 'User login'),
            new InputOption('user-email', '', InputOption::VALUE_REQUIRED, 'User email'),
            new InputOption('password', '', InputOption::VALUE_REQUIRED, 'Set user password'),
            new InputOption('enabled', '', InputOption::VALUE_REQUIRED, 'Set user status'),
        ))
            ->setDescription('Manage eZ Platform Repository users.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command might do something unexpected.

  Show basic info about user
  <info>php %command.full_name% --user-login=USER-LOGIN | --user-id=USER-ID | --user-email=USER-EMAIL</info>              
                
  Sets user password
  <info>php %command.full_name% --user-id=USER-ID --password=PASSWORD</info>
   
  Disable user
  <info>php %command.full_name% --user-id=USER-ID --enabled=0</info>
  
EOF
);
    }
    
    protected function initServices() {
        $this->repository = $this->getContainer()->get('ezpublish.api.repository');
        $this->userService = $this->repository->getUserService();
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
        
        $userId = null;
        $userLogin = null;
        $userEmail = null;
        $userPassword = null;
        $userEnabled = null;
        $action = self::CMD_ACTION_VIEW;
        
        if (null === $input->getOption('user-id') && 
            null === $input->getOption('user-login') &&
            null === $input->getOption('user-email')) {
            throw new \InvalidArgumentException('Target user must be specified by user-id, user-login or user-email argument.');
        }
        
        
        if (null !== $input->getOption('admin-id')) {
            $admin_id = intval($input->getOption('admin-id'));
            if ($admin_id == 0) {
                throw new \InvalidArgumentException('User id must be numeric.');
            }
            $this->adminUserId = $admin_id;
        }
        
        if (null !== $input->getOption('user-id')) {
            $user_id = intval($input->getOption('user-id'));
            if ($user_id == 0) {
                throw new \InvalidArgumentException('User id must be numeric.');
            }
            $userId = $user_id;
        }
        
        if (null !== $input->getOption('user-login')) {
            $userLogin = $input->getOption('user-login');
        }
        
        if (null !== $input->getOption('user-email')) {
            $userEmail = $input->getOption('user-email');
        }
        
        if (null !== $input->getOption('enabled')) {
            $userEnabled = $input->getOption('enabled') ? 1 : 0;
            $action = self::CMD_ACTION_UPDATE;
        }
        
        if (null !== $input->getOption('password')) {
            $userPassword = $input->getOption('password');
            $action = self::CMD_ACTION_UPDATE;
        }
        
        // init services
        $this->initServices();
        
        // we need escalated privileges
        $user = $this->userService->loadUser( $this->adminUserId );
        $output->writeln('Operating repository as <info>'. $user->id . ':' . $user->login . '</info>');
        $this->repository->setCurrentUser($user);
        
        $target_user = null;
        $target_users = null;
        
        try {
            if ($userId !== null) {
                $target_user = $this->userService->loadUser( $userId );
            } else if ($userLogin !==null) {
                $target_user = $this->userService->loadUserByLogin( $userLogin );
            } else if ($userEmail !==null) {
                $target_users = $this->userService->loadUsersByEmail( $userEmail );
            }
        } catch (\eZ\Publish\API\Repository\Exceptions\NotFoundException $e) {
            throw new \InvalidArgumentException('Specified user not found. ' . $e->getMessage());
        }
        
        if ($target_users !== null && is_array($target_users)) {
            if  (count($target_users) == 1) {
                $target_user = $target_users[0];
            } else if (count($target_users) == 0) {
               throw new \InvalidArgumentException('No user with email ' . $userEmail . ' found.');
            } else {
                $output->writeln('Several users found by email <info>' . $userEmail . '</info>');
                foreach ($target_users as $key => $user_obj) {
                    $output->writeln($user_obj->id . ':' . $user_obj->login . ':' . $user_obj->email);
                }
                throw new \InvalidArgumentException('Could not uniquely identify user.');
            }
        }
        
        if ($action == self::CMD_ACTION_VIEW) {
            if ($target_user !== null) {
                $output->writeln('Id:<info>' . $target_user->id . '</info>');
                $output->writeln('Login:<info>' . $target_user->login . '</info>');
                $output->writeln('Email:<info>'. $target_user->email . '</info>');
                $output->writeln('Enabled:<info>'. $target_user->enabled . '</info>');
                $output->writeln('Max login:<info>'. $target_user->maxLogin . '</info>');
                $output->writeln('Password:<info>'. $target_user->passwordHash . ' [' . $target_user->hashAlgorithm. ']</info>');
                $output->writeln('Content Published:' . $target_user->content->versionInfo->contentInfo->publishedDate->format('Y-m-d H:i:s'));
                $output->writeln('Content Modified:' . $target_user->content->versionInfo->contentInfo->modificationDate->format('Y-m-d H:i:s'));
            } 
        } else if ($action == self::CMD_ACTION_UPDATE) {
            $output->writeln('Updating user <info>' . $target_user->login . '</info> ...');
            $this->updateUser($target_user, $userPassword, $userEnabled);
        }
        
        
        $output->success('Done');
    }


    private function updateUser($targetUser, $password, $enabled) {
        $userUpdateStruct = $this->userService->newUserUpdateStruct();
        if ($password !== null) $userUpdateStruct->password = $password;
        if ($enabled !== null) $userUpdateStruct->enabled = $enabled;
        $this->userService->updateUser($targetUser, $userUpdateStruct);
    }
}
