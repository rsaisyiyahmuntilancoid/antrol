pipeline {
	agent any

	options {
		timestamps()
		ansiColor('xterm')
	}

    tools {
        nodejs 'node-22'
    }

	parameters {
		string(name: 'REMOTE_HOST', defaultValue: '192.168.30.201', description: 'Target server hostname or IP')
		string(name: 'REMOTE_PORT', defaultValue: '22', description: 'SSH port on target server')
		string(name: 'REMOTE_USER', defaultValue: 'root', description: 'SSH user on target server')
		string(name: 'REMOTE_BASE_DIR', defaultValue: '/www/wwwroot/antrol.rsam.co.id', description: 'Base directory on target server')
		string(name: 'SSH_CREDENTIALS_ID', defaultValue: '201-key', description: 'Jenkins SSH key credentials ID')
		string(name: 'KEEP_RELEASES', defaultValue: '5', description: 'How many releases/backups to keep')
		string(name: 'HEALTHCHECK_COMMAND', defaultValue: 'test -f current/artisan', description: 'Command run on server after deploy to validate release')
	}

	environment {
		RELEASE_ID = ''
		PREVIOUS_RELEASE_FILE = '.previous_release_path'
	}

	stages {
		stage('Checkout') {
			steps {
				checkout scm
			}
		}

		stage('Initialize Release Metadata') {
			steps {
				script {
					env.RELEASE_ID = "${env.BUILD_NUMBER}-${new Date().format('yyyyMMddHHmmss', TimeZone.getTimeZone('UTC'))}"
				}
			}
		}

		stage('Build Assets') {
			steps {
				sh '''
					set -e
					npm i
					npm run build
				'''
			}
		}

        stage('Deploy to Server') {
            steps {
                sshagent(credentials: [params.SSH_CREDENTIALS_ID]) {
                    script {
                        def remote = "${params.REMOTE_USER}@${params.REMOTE_HOST}"
                        def baseDir = params.REMOTE_BASE_DIR
                        def targetRelease = "${baseDir}/releases/${env.RELEASE_ID}"
                        def keep = params.KEEP_RELEASES.toInteger() + 1
        
                        sh """
                            set -e
        
                            ssh -p ${params.REMOTE_PORT} -o StrictHostKeyChecking=no ${remote} '
                                mkdir -p ${baseDir}/releases ${baseDir}/backups
                            '
        
                            ssh -p ${params.REMOTE_PORT} -o StrictHostKeyChecking=no ${remote} '
                                if [ -L ${baseDir}/current ]; then
                                    readlink -f ${baseDir}/current
                                fi
                            ' > ${env.PREVIOUS_RELEASE_FILE}
        
                            ssh -p ${params.REMOTE_PORT} -o StrictHostKeyChecking=no ${remote} '
                                if [ -L ${baseDir}/current ]; then
                                    PREV=\$(readlink -f ${baseDir}/current)
                                    if [ -n "\$PREV" ] && [ -d "\$PREV" ]; then
                                        cp -a "\$PREV" ${baseDir}/backups/backup-${env.RELEASE_ID}
                                    fi
                                fi
                            '
        
                            rsync -az --delete \
                                --exclude='.git' \
                                --exclude='.github' \
                                --exclude='node_modules' \
                                --exclude='storage/logs/*' \
                                -e "ssh -p ${params.REMOTE_PORT} -o StrictHostKeyChecking=no" \
                                ./ ${remote}:${targetRelease}/

                            ssh -p ${params.REMOTE_PORT} -o StrictHostKeyChecking=no ${remote} '
                                ln -sfn ${baseDir}/.env ${targetRelease}/.env
                            '
        
                            ssh -p ${params.REMOTE_PORT} -o StrictHostKeyChecking=no ${remote} '
                                ln -sfn ${targetRelease} ${baseDir}/current
                            '
        
                            ssh -p ${params.REMOTE_PORT} -o StrictHostKeyChecking=no ${remote} '
                                export PATH=/www/server/php/83/bin:$PATH
                                cd ${baseDir}/current && composer install && php artisan optimize && php artisan config:clear
                            '
        
                            ssh -p ${params.REMOTE_PORT} -o StrictHostKeyChecking=no ${remote} '
                                # export PATH=/www/server/php/83/bin:$PATH
                                # cd ${baseDir} && ${params.HEALTHCHECK_COMMAND}
                            '
                        """
                    }
                }
            }
        }
        
		// stage('Cleanup Old Releases') {
		// 	steps {
		// 		sshagent(credentials: [params.SSH_CREDENTIALS_ID]) {
		// 			script {
		// 				def remote = "${params.REMOTE_USER}@${params.REMOTE_HOST}"

		// 				sh """
		// 					set -e
		// 					ssh -p ${params.REMOTE_PORT} -o StrictHostKeyChecking=no ${remote} '\
		// 						cd ${params.REMOTE_BASE_DIR}/releases && ls -1dt */ 2>/dev/null | tail -n +${keep} | xargs -r rm -rf;\
		// 						cd ${params.REMOTE_BASE_DIR}/backups && ls -1dt */ 2>/dev/null | tail -n +${keep} | xargs -r rm -rf\
		// 					'
		// 				"""
		// 			}
		// 		}
		// 	}
		// }
	}

	post {
		failure {
			script {
				// if (fileExists(env.PREVIOUS_RELEASE_FILE)) {
				// 	def previousRelease = readFile(env.PREVIOUS_RELEASE_FILE).trim()
				// 	if (previousRelease) {
				// 		sshagent(credentials: [params.SSH_CREDENTIALS_ID]) {
				// 			sh """
				// 				set -e
				// 				ssh -p ${params.REMOTE_PORT} -o StrictHostKeyChecking=no ${params.REMOTE_USER}@${params.REMOTE_HOST} '\
				// 					if [ -d ${previousRelease} ]; then\
				// 						ln -sfn ${previousRelease} ${params.REMOTE_BASE_DIR}/current;\
				// 					fi\
				// 				'
				// 			"""
				// 		}
				// 		echo "Rollback completed: current -> ${previousRelease}"
				// 	} else {
				// 		echo 'Rollback skipped: no previous release found.'
				// 	}
				// } else {
				// 	echo 'Rollback skipped: previous release metadata file not found.'
				// }
                echo "Deployment failed"
			}
		}
	}
}
