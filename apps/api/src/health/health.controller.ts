import { Controller, Get } from '@nestjs/common';
import { ApiTags } from '@nestjs/swagger';
import { Publico } from '../auth/publico.decorator';

@ApiTags('Saúde')
@Controller('health')
export class HealthController {
  @Publico()
  @Get()
  verificar() {
    return { status: 'ok' };
  }
}
