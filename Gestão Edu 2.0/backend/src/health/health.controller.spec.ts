import { ServiceUnavailableException } from '@nestjs/common';
import { DataSource } from 'typeorm';
import { HealthController } from './health.controller';
import { RedisService } from '../infrastructure/redis.service';

// US003/CT08: cobre respostas de vida e prontidão sem iniciar serviços externos.
describe('HealthController', () => {
  const dataSource = { query: jest.fn() } as unknown as DataSource;
  const redis = { ping: jest.fn() } as unknown as RedisService;
  const controller = new HealthController(dataSource, redis);

  beforeEach(() => jest.clearAllMocks());

  it('reports the process as alive', () => {
    expect(controller.health()).toEqual({ status: 'ok' });
  });

  it('reports ready when database and Redis respond', async () => {
    jest.mocked(dataSource.query).mockResolvedValue([{ 1: 1 }]);
    jest.mocked(redis.ping).mockResolvedValue(true);

    await expect(controller.ready()).resolves.toEqual({ status: 'ready' });
  });

  it('reports unavailable when a dependency fails', async () => {
    jest.mocked(dataSource.query).mockRejectedValue(new Error('database unavailable'));

    await expect(controller.ready()).rejects.toBeInstanceOf(ServiceUnavailableException);
  });
});
