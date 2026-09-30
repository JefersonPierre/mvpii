import { Body, Controller, Get, Param, ParseIntPipe, Patch, Post, Query } from '@nestjs/common';
import { ApiBearerAuth, ApiTags } from '@nestjs/swagger';
import { UsuarioAtual, UsuarioLogado } from '../auth/usuario-logado.decorator';
import { CriarUsuarioDto, EditarUsuarioDto, FiltroUsuariosDto, SituacaoUsuarioDto } from './usuarios.dto';
import { UsuariosService } from './usuarios.service';

@ApiTags('Usuários')
@ApiBearerAuth()
@Controller('usuarios')
export class UsuariosController {
  constructor(private readonly usuarios: UsuariosService) {}

  @Get()
  listar(@Query() filtro: FiltroUsuariosDto) {
    return this.usuarios.listar(filtro);
  }

  @Get(':id')
  buscar(@Param('id', ParseIntPipe) id: number) {
    return this.usuarios.buscar(id);
  }

  @Post()
  criar(@Body() dto: CriarUsuarioDto, @UsuarioAtual() eu: UsuarioLogado) {
    return this.usuarios.criar(dto, eu.id);
  }

  @Patch(':id')
  editar(@Param('id', ParseIntPipe) id: number, @Body() dto: EditarUsuarioDto, @UsuarioAtual() eu: UsuarioLogado) {
    return this.usuarios.editar(id, dto, eu.id);
  }

  @Patch(':id/situacao')
  alterarSituacao(
    @Param('id', ParseIntPipe) id: number,
    @Body() dto: SituacaoUsuarioDto,
    @UsuarioAtual() eu: UsuarioLogado,
  ) {
    return this.usuarios.alterarSituacao(id, dto.ativo, eu.id);
  }

  @Get(':id/historico')
  historico(@Param('id', ParseIntPipe) id: number) {
    return this.usuarios.historico(id);
  }
}
