cd /home/ubuntu/raptor
git pull origin main
sudo docker cp /home/ubuntu/raptor/nginx.conf raptor-nginx:/etc/nginx/conf.d/default.conf
sudo docker exec raptor-web sh -c "echo 'upload_max_filesize = 64M' > /usr/local/etc/php/conf.d/uploads.ini"
sudo docker exec raptor-web sh -c "echo 'post_max_size = 64M' >> /usr/local/etc/php/conf.d/uploads.ini"
sudo docker restart raptor-web raptor-nginx
