import { Injectable, OnModuleDestroy, OnModuleInit } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { createClient, RedisClientType } from 'redis';

// US003/CT08: Redis fica reservado a cache/sessões; healthcheck testa conexão sem expor dados.
@Injectable()
export class RedisService implements OnModuleInit, OnModuleDestroy {
  private readonly client: RedisClientType;

  constructor(config: ConfigService) {
    this.client = createClient({
      socket: {
        host: config.getOrThrow<string>('REDIS_HOST'),
        port: Number(config.get<string>('REDIS_PORT', '6379')),
        reconnectStrategy: (retries: number) => Math.min(retries * 250, 3000),
      },
      password: config.getOrThrow<string>('REDIS_PASSWORD'),
    });
    this.client.on('error', (error) => console.error('Redis connection error:', error.message));
  }

  async onModuleInit(): Promise<void> {
    await this.client.connect();
  }

  async ping(): Promise<boolean> {
    return (await this.client.ping()) === 'PONG';
  }

  async onModuleDestroy(): Promise<void> {
    if (this.client.isOpen) await this.client.quit();
  }
}
