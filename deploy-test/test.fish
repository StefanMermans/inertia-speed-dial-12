function setup
    set -l test_dir ./inertia-speed-dial-deploy/
    set -l branch deploy-test

    if test -e $test_dir
        echo "Cleaning up old test folder 🧹"
        cd $test_dir
        docker compose down --remove-orphans
        cd ..
        rm -rf $test_dir
    end

    echo "Running test 🚀"
    mkdir $test_dir
    cd $test_dir

    git clone git@github.com:StefanMermans/iniertia-speed-dial-12.git .
    git switch $branch
end

function local-only-addon
    cp -r ../../storage/app/public/images/ ./storage/app/public/images
end

function run
    echo "Starting test 🏁"
    cp .env.prod .env
    local-only-addon
    echo "ADMIN_USERNAME=\"stefanmermans99@gmail.com\"" >> .env
    echo "ADMIN_PASSWORD=\"Welkom01\"" >> .env
    echo "APP_KEY=\"base64:EnZEpRlok7A2MvTqtG7v9yPKP+ECIdaUE+L+yvF83oc=\"" >> .env
    docker compose up --build -d
    docker compose exec app php artisan migrate --force
    docker compose exec app php artisan optimize
end

# TODO: uncomment
setup

# TODO: remove
# cd ./inertia-speed-dial-deploy/
run

