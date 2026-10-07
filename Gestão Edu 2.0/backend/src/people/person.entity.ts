import { Column, CreateDateColumn, Entity, OneToOne, PrimaryColumn, UpdateDateColumn } from 'typeorm';
import { UserAccount } from './user-account.entity';
import { MunicipalServerProfile } from './municipal-server-profile.entity';

// US003/CT01: pessoa existe independentemente de conta ou vínculo funcional.
@Entity('people')
export class Person {
  @PrimaryColumn({ type: 'char', length: 36 }) uuid!: string;
  @Column({ name: 'full_name', type: 'varchar', length: 200 }) fullName!: string;
  @Column({ type: 'char', length: 11, nullable: true }) cpf!: string | null;
  @CreateDateColumn({ name: 'created_at', type: 'datetime', precision: 6 }) createdAt!: Date;
  @UpdateDateColumn({ name: 'updated_at', type: 'datetime', precision: 6 }) updatedAt!: Date;
  @OneToOne(() => UserAccount, (account) => account.person) account?: UserAccount;
  @OneToOne(() => MunicipalServerProfile, (profile) => profile.person) municipalServer?: MunicipalServerProfile;
}
