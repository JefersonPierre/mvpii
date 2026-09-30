import { Body, Controller, Get, HttpCode, Post } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { LoginDto, RecuperarSenhaDto, RedefinirSenhaDto } from './auth.dto';
import { AuthService } from './auth.service';
import { Publico } from './publico.decorator';
import { UsuarioAtual, UsuarioLogado } from './usuario-logado.decorator';

@ApiTags('Acesso')
@Controller('auth')
export class AuthController {
  constructor(private readonly auth: AuthService) {}

  @Publico()
  @Post('login')
  @HttpCode(200)
  login(@Body() dto: LoginDto) {
    return this.auth.login(dto.email, dto.senha);
  }

  @Publico()
  @Post('recuperar-senha')
  @HttpCode(200)
  async recuperarSenha(@Body() dto: RecuperarSenhaDto) {
    await this.auth.solicitarRecuperacao(dto.email);
    return { mensagem: 'Se o e-mail estiver cadastrado, você receberá um link para criar uma nova senha.' };
  }

  @Publico()
  @Post('redefinir-senha')
  @HttpCode(200)
  async redefinirSenha(@Body() dto: RedefinirSenhaDto) {
    await this.auth.redefinirSenha(dto.token, dto.novaSenha);
    return { mensagem: 'Senha alterada. Entre com a nova senha.' };
  }

  @ApiBearerAuth()
  @Get('me')
  me(@UsuarioAtual() usuario: UsuarioLogado) {
    return usuario;
  }
}
