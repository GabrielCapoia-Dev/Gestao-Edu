import { Controller, Get, ServiceUnavailableException } from '@nestjs/common';
import { DataSource } from 'typeorm';
import { RedisService } from '../infrastructure/redis.service';

// US003/CT08: endpoints distinguem processo vivo de dependências prontas.
@Controller('')
export class HealthController {
  constructor(private readonly dataSource: DataSource, private readonly redis: RedisService) {}

  @Get('health')
  health(): { status: string } {
    return { status: 'ok' };
  }

  @Get('ready')
  async ready(): Promise<{ status: string }> {
    try {
      await this.dataSource.query('SELECT 1');
      if (!(await this.redis.ping())) throw new Error('Redis did not answer PONG');
      return { status: 'ready' };
    } catch {
      throw new ServiceUnavailableException({ status: 'not-ready' });
    }
  }
}
