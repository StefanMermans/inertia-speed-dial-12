function setup
    set -l test_dir ./inertia-speed-dial-deploy/

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
end

function run
    echo "Starting test 🏁"
    cp ../.env .env
    docker compose up --build -d
end

# TODO: uncomment
setup

# TODO: remove
# cd ./inertia-speed-dial-deploy/
run

