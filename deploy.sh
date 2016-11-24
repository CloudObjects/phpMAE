docker build -t cloudobjects/phpmae .
docker login -u $DOCKER_HUB_USER -p $DOCKER_HUB_PW
docker push cloudobjects/phpmae