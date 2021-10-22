# laravel-rabbitmq-mongodb
laravel 框架下使用rabbitmq和mongodb

docker-compose.yml 是一个docker环境下的PHP集成开发环境，主要包括RabbitMQ、MongoDB、Redis、Mysql、PHP、Nginx、Kafka等

使用：只需要在docker-compose.yml文件目录下执行 docker-compose up -d

进入容器方法： docker exec -it {容器名称/容器ID} bash 





go 使用grpc

1、protobuf协议编译器是用c++编写的，根据自己的操作系统下载对应版本的protoc编译器：https://github.com/protocolbuffers/protobuf/releases，解压后将protoc.exe拷贝到GOPATH/bin目录下。
2、分别执行 go install google.golang.org/protobuf/cmd/protoc-gen-go@v1.26   go install google.golang.org/grpc/cmd/protoc-gen-go-grpc@v1.1
3、执行 go get google.golang.org/grpc
4、配置.proto 文件
5、执行 protoc --go_out=. --go_opt=paths=source_relative --go-grpc_out=. --go-grpc_opt=paths=source_relative ./helloworld.proto  //在.proto文件目录执行，helloworld为.proto文件名

//下面为.proto文件内容
syntax = "proto3";

option go_package ="./;golang";

package hello_grpc;

message Req {
    string message = 1;
}

message Res {
    string message = 1;
}

service HelloGRPC{
    rpc SayHi (Req) returns (Res);
}

